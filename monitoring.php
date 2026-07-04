<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();
checkAdmin();

?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统监控 - 管理面板</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap">
    <style>
        /* 完全一致的CSS样式 */
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
            --red-500: #ef4444;
            --red-600: #dc2626;
            --green-500: #10b981;
            --green-600: #059669;
            --yellow-500: #f59e0b;
            --yellow-600: #d97706;
            --purple-500: #8b5cf6;
            --purple-600: #7c3aed;
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
            max-width: 64rem;
            margin: 0 auto;
            min-height: 100vh;
            background-color: var(--gray-50);
        }
        
        /* Header - 完全一致 */
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
            margin-left: 1.5rem;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .header-actions {
            margin-left:auto;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        /* Main Content - 完全一致 */
        .main-content {
            padding: 1rem;
        }
        
        /* Cards - 完全一致 */
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
        
        /* 监控数据网格 - 每行2个，始终两列 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 1.5rem;
        }
        
        /* 统计卡片样式 */
        .stat-card {
            padding: 1.5rem;
            border-radius: 0.5rem;
            background: var(--white);
            border: 1px solid var(--gray-200);
            transition: all 0.2s;
        }
        
        .stat-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .stat-card:nth-child(1) .stat-icon { background-color: rgba(139, 92, 246, 0.1); color: var(--purple-500); }
        .stat-card:nth-child(2) .stat-icon { background-color: rgba(16, 185, 129, 0.1); color: var(--green-500); }
        .stat-card:nth-child(3) .stat-icon { background-color: rgba(59, 130, 246, 0.1); color: var(--blue-500); }
        .stat-card:nth-child(4) .stat-icon { background-color: rgba(245, 158, 11, 0.1); color: var(--yellow-500); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }
        
        .stat-trend {
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        /* 进度条样式 */
        .progress-bar {
            width: 100%;
            height: 0.375rem;
            background-color: var(--gray-200);
            border-radius: 0.25rem;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 0.25rem;
            transition: width 0.5s ease;
        }
        
        .progress-fill.low { background-color: var(--green-500); }
        .progress-fill.medium { background-color: var(--yellow-500); }
        .progress-fill.high { background-color: var(--red-500); }
        
        /* 服务器状态表单样式 - 模拟网站设置页面的表单样式 */
        .form-group {
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
            background-color: var(--gray-50);
        }
        
        /* 服务器状态网格布局 */
        .server-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .server-info-item {
            display: flex;
            flex-direction: column;
        }
        
        /* 状态指示器 */
        .status-indicator {
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .status-indicator.online {
            background-color: var(--green-500);
            animation: pulse 2s infinite;
        }
        
        .status-indicator.offline {
            background-color: var(--red-500);
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* 按钮 - 完全一致 */
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
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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
        
        .btn-icon {
            width: 1em;
            height: 1em;
        }
        
        /* 更新时间和加载指示器 */
        .update-time {
            text-align: center;
            color: var(--gray-500);
            font-size: 0.875rem;
            margin-top: 1.5rem;
            padding: 1rem;
        }
        
        .loading {
            text-align: center;
            color: var(--gray-600);
        }
        
        .spinner {
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-top: 3px solid var(--gray-600);
            border-radius: 50%;
            width: 2.5rem;
            height: 2.5rem;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* 错误提示 */
        .error-card {
            text-align: center;
            padding: 2rem;
        }
        
        .error-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        /* 在线用户样式 */
        .users-section {
            margin-bottom: 2rem;
        }
        
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
        
        .user-card {
            padding: 1rem;
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
            background-color: var(--white);
        }
        
        .user-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 0.875rem;
            font-weight: 600;
            margin-right: 1rem;
        }
        
        .user-card.agent .user-avatar {
            background-color: var(--green-500);
        }
        
        .user-card.customer .user-avatar {
            background-color: var(--blue-500);
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }
        
        .user-type {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        .user-details {
            border-top: 1px solid var(--gray-200);
            padding-top: 1rem;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.75rem;
        }
        
        .detail-item:last-child {
            margin-bottom: 0;
        }
        
        .detail-label {
            color: var(--gray-600);
        }
        
        .detail-value {
            color: var(--gray-900);
            font-weight: 500;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray-500);
        }
        
        .empty-state .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        /* Helper classes */
        .mb-4 {
            margin-bottom: 1rem;
        }
        
        .mb-6 {
            margin-bottom: 1.5rem;
        }
        
        .mt-4 {
            margin-top: 1rem;
        }
        
        .mt-6 {
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header - 完全一致 -->
        <header class="header">
            <div class="header-content">
                <a href="javascript:void(0)" onclick="window.parent.postMessage('closeModal', '*')" class="back-link">
                    <svg class="back-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10" clip-rule="evenodd"></path>
                    </svg>
                    <span class="back-text">返回</span>
                </a>
                <h1 class="title">系统监控</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="refreshData()">
                        <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.311.311a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V2.929a.75.75 0 0 0-1.5 0v2.43l-.311-.311A7 7 0 0 0 3.239 8.188a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.311h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clip-rule="evenodd" />
                        </svg>
                        刷新
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- 系统概览卡片 -->
            <div class="card">
                <h2 class="card-title">系统概览</h2>
                <div class="stats-grid" id="stats-container">
                    <div class="loading">
                        <div class="spinner"></div>
                        <div>正在加载监控数据...</div>
                    </div>
                </div>
            </div>

            <!-- 服务器状态卡片 - 表单样式 -->
            <div class="card">
                <h2 class="card-title">服务器状态</h2>
                <div id="server-info" class="loading">
                    <div class="spinner"></div>
                    <div>正在加载服务器信息...</div>
                </div>
            </div>

            <!-- 在线用户卡片 -->
            <div class="card" id="users-container">
                <h2 class="card-title">在线用户</h2>
                <div class="loading">
                    <div class="spinner"></div>
                    <div>正在加载用户数据...</div>
                </div>
            </div>

            <!-- 更新时间 -->
            <div class="update-time" id="last-update">
                最后更新：从未更新
            </div>
        </main>
    </div>

    <script>
        let autoRefreshInterval;
        
        async function refreshData() {
            try {
                const response = await fetch('/api/monitoring/stats');
                
                if (!response.ok) {
                    throw new Error(`HTTP 错误：${response.status}`);
                }
                
                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('响应内容:', responseText);
                    throw new Error('服务器返回了无效的 JSON 格式');
                }
                
                if (data.success) {
                    renderStats(data.data);
                    renderServerInfo(data.data);
                    renderOnlineUsers(data.data);
                    updateLastUpdateTime();
                } else {
                    showError('获取数据失败：' + (data.message || '未知错误'));
                }
            } catch (error) {
                console.error('刷新数据失败:', error);
                showError('网络错误：' + error.message);
            }
        }
        
        function renderStats(data) {
            const container = document.getElementById('stats-container');
            
            container.innerHTML = `
                <!-- 第一行：2个卡片 -->
                <div class="stat-card">
                    <div class="stat-icon">
                    <svg t="1778618145521" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="6355" width="24" height="24"><path d="M475.428571 475.428571 0 475.428571 0 0l475.428571 0L475.428571 475.428571zM394.971429 80.457143 80.457143 80.457143l0 314.514286 314.514286 0L394.971429 80.457143z" p-id="6356"></path><path d="M1024 475.428571 548.571429 475.428571 548.571429 0 1024 0 1024 475.428571zM943.542857 80.457143 629.028571 80.457143l0 314.514286 314.514286 0L943.542857 80.457143z" p-id="6357"></path><path d="M475.428571 1024 0 1024 0 548.571429l475.428571 0L475.428571 1024zM394.971429 629.028571 80.457143 629.028571l0 314.514286 314.514286 0L394.971429 629.028571z" p-id="6358"></path><path d="M1024 1024 548.571429 1024 548.571429 548.571429 1024 548.571429 1024 1024zM943.542857 629.028571 629.028571 629.028571l0 314.514286 314.514286 0L943.542857 629.028571z" p-id="6359"></path></svg>
                    </div>
                    <div class="stat-value">${data.total_connections}</div>
                    <div class="stat-label">总连接数</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><svg t="1778618193303" class="icon" viewBox="0 0 1025 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="7496" width="24" height="24"><path d="M406.766493 519.191123C402.472299 519.191123 398.112041 518.365316 393.916945 516.581574 294.159525 474.432413 229.71359 377.185445 229.71359 268.872671 229.71359 120.623897 350.347396-0.00991 498.59617-0.00991 646.844945-0.00991 767.445719 120.623897 767.445719 268.872671 767.445719 373.849187 705.741461 469.873961 610.245203 513.509574 593.629977 521.073961 574.041848 513.806865 566.444428 497.224671 558.880041 480.609445 566.18017 461.021316 582.795396 453.456929 654.838751 420.523768 701.381203 348.050994 701.381203 268.872671 701.381203 157.025445 610.410364 66.054606 498.59617 66.054606 386.781977 66.054606 295.778106 157.025445 295.778106 268.872671 295.778106 350.594477 344.40159 423.92609 419.649074 455.736155 436.429461 462.83809 444.32417 482.228026 437.189203 499.041445 431.871009 511.626735 419.649074 519.191123 406.766493 519.191123" fill="#231814" p-id="7497"></path><path d="M673.71999 996.54689 673.686957 996.54689 103.087732 996.018374C67.148635 995.95231 34.413667 978.147923 15.519215 948.385858-2.714591 919.680826-4.960785 884.171148 9.44128 853.385084 59.485151 746.525729 190.623215 566.532955 506.708893 561.644181 831.614183 555.698374 949.803603 748.474632 991.325151 863.327794 1002.225796 893.486245 997.832506 925.989987 979.202312 952.547923 959.878441 980.096826 927.936248 996.54689 893.780893 996.54689L811.365409 996.54689C793.131603 996.54689 778.333151 981.781471 778.333151 963.514632 778.333151 945.247794 793.131603 930.482374 811.365409 930.482374L893.780893 930.482374C906.432248 930.482374 918.125667 924.536568 925.128506 914.62689 928.69599 909.50689 933.981151 899.002632 929.191474 885.756697 885.787086 765.750503 776.351215 623.282374 507.765925 627.708697 241.59199 631.837729 122.411603 767.930632 69.295732 881.396439 64.406957 891.768568 65.166699 903.296826 71.310699 912.975277 78.04928 923.578632 89.973925 929.920826 103.186828 929.953858L673.753022 930.482374C691.986828 930.515406 706.752248 945.280826 706.752248 963.547665 706.752248 981.781471 691.953796 996.54689 673.71999 996.54689" fill="#231814" p-id="7498"></path></svg></div>
                    <div class="stat-value">${data.online_agents_count || data.online_agents}</div>
                    <div class="stat-label">在线客服</div>
                </div>
                
                <!-- 第二行：2个卡片 -->
                <div class="stat-card">
                    <div class="stat-icon"><svg t="1778618212450" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="9092" width="24" height="24"><path d="M505.557333 133.845333A427.072 427.072 0 0 1 814.293333 359.04l3.84 7.637333 3.306667 6.890667L1024 272.298667v479.402666l-202.581333-101.290666-3.285334 6.933333a426.88 426.88 0 0 1-366.378666 238.336l-8.512 0.234667-8.256 0.085333C219.52 896 38.890667 735.466667 11.797333 524.096l-0.853333-7.402667L10.389333 512l1.386667-12.074667a423.893333 423.893333 0 0 1 67.370667-180.864l-0.426667 2.026667A213.568 213.568 0 0 1 137.173333 211.818667 212.757333 212.757333 0 0 1 288 149.333333c117.845333 0 213.333333 95.509333 213.333333 213.333334 0 115.84-92.288 210.090667-207.36 213.248L288 576v-85.333333a128 128 0 1 0-125.546667-153.024l-1.066666 6.186666-0.874667 7.424 0.213333 0.106667a338.986667 338.986667 0 0 0-63.786666 155.626667l-0.64 5.12 0.298666 2.261333c21.546667 163.669333 158.357333 289.066667 323.925334 296l7.317333 0.213333 7.146667 0.085334c145.28 0 273.024-91.605333 321.28-225.706667l2.410666-6.954667 15.616-46.549333L938.666667 613.632v-203.285333l-164.373334 82.218666-15.616-46.549333A341.568 341.568 0 0 0 494.293333 218.496l11.285334-84.650667zM330.666667 320v85.333333h-85.333334v-85.333333h85.333334z" fill="#333333" p-id="9093"></path></svg></div>
                    <div class="stat-value">${data.online_customers_count || data.online_customers}</div>
                    <div class="stat-label">在线鱼</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><svg t="1778618284158" class="icon" viewBox="0 0 1066 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="11908" width="24" height="24"><path d="M298.666667 213.333333H256a21.333333 21.333333 0 0 0-21.333333 21.333334v42.666666A21.333333 21.333333 0 0 0 256 298.666667h42.666667a21.333333 21.333333 0 0 0 21.333333-21.333334v-42.666666A21.333333 21.333333 0 0 0 298.666667 213.333333zM426.666667 213.333333h42.666666a21.333333 21.333333 0 0 1 21.333334 21.333334v42.666666a21.333333 21.333333 0 0 1-21.333334 21.333334h-42.666666a21.333333 21.333333 0 0 1-21.333334-21.333334v-42.666666A21.333333 21.333333 0 0 1 426.666667 213.333333z" fill="#000000" p-id="11909"></path><path d="M64 85.333333a42.666667 42.666667 0 0 1 42.666667-42.666666h853.333333a42.666667 42.666667 0 0 1 42.666667 42.666666v341.333334a42.666667 42.666667 0 0 1-42.666667 42.666666h-853.333333a42.666667 42.666667 0 0 1-42.666667-42.666666V85.333333z m85.333333 42.666667v256h768V128h-768z" fill="#000000" p-id="11910"></path><path d="M298.666667 725.333333H256a21.333333 21.333333 0 0 0-21.333333 21.333334v42.666666a21.333333 21.333333 0 0 0 21.333333 21.333334h42.666667a21.333333 21.333333 0 0 0 21.333333-21.333334v-42.666666A21.333333 21.333333 0 0 0 298.666667 725.333333zM469.333333 725.333333h-42.666666a21.333333 21.333333 0 0 0-21.333334 21.333334v42.666666a21.333333 21.333333 0 0 0 21.333334 21.333334h42.666666a21.333333 21.333333 0 0 0 21.333334-21.333334v-42.666666a21.333333 21.333333 0 0 0-21.333334-21.333334z" fill="#000000" p-id="11911"></path><path d="M64 597.333333a42.666667 42.666667 0 0 1 42.666667-42.666666h853.333333a42.666667 42.666667 0 0 1 42.666667 42.666666v341.333334a42.666667 42.666667 0 0 1-42.666667 42.666666h-853.333333a42.666667 42.666667 0 0 1-42.666667-42.666666v-341.333334z m85.333333 42.666667v256h768v-256h-768z" fill="#000000" p-id="11912"></path></svg></div>
                    <div class="stat-value">${data.memory_usage}</div>
                    <div class="stat-label">内存占用</div>
                    <div class="stat-trend">内存使用率：${data.memory_percentage}%</div>
                    <div class="progress-bar">
                        <div class="progress-fill ${getUsageClass(data.memory_percentage)}" style="width: ${data.memory_percentage}%"></div>
                    </div>
                </div>
            `;
        }
        
        function renderServerInfo(data) {
            const container = document.getElementById('server-info');
            
            container.innerHTML = `
                <div class="server-info-grid">
                    <div class="server-info-item">
                        <label class="form-label">服务器状态</label>
                        <div class="input">
                            <span class="status-indicator online"></span>
                            正常运行
                        </div>
                    </div>
                    
                    <div class="server-info-item">
                        <label class="form-label">WebSocket 服务</label>
                        <div class="input">
                            <span class="status-indicator ${data.websocket_status === 'running' ? 'online' : 'offline'}"></span>
                            ${data.websocket_status === 'running' ? '运行中' : '未运行'}
                        </div>
                    </div>
                    
                    <div class="server-info-item">
                        <label class="form-label">HTTP API 服务</label>
                        <div class="input">
                            <span class="status-indicator ${data.http_api_status === 'running' ? 'online' : 'offline'}"></span>
                            ${data.http_api_status === 'running' ? '运行中' : '未运行'}
                        </div>
                    </div>
                    
                    <div class="server-info-item">
                        <label class="form-label">系统运行时间</label>
                        <div class="input">${data.uptime}</div>
                    </div>
                    
                    <div class="server-info-item">
                        <label class="form-label">CPU 使用率</label>
                        <div class="input">${data.cpu_usage}%</div>
                        <div class="progress-bar">
                            <div class="progress-fill ${getUsageClass(data.cpu_usage)}" style="width: ${data.cpu_usage}%"></div>
                        </div>
                    </div>
                    
                    <div class="server-info-item">
                        <label class="form-label">磁盘使用率</label>
                        <div class="input">${data.disk_usage}%</div>
                        <div class="progress-bar">
                            <div class="progress-fill ${getUsageClass(data.disk_usage)}" style="width: ${data.disk_usage}%"></div>
                        </div>
                    </div>
                    
                    <div class="server-info-item">
                        <label class="form-label">PHP 版本</label>
                        <div class="input">${data.php_version}</div>
                    </div>
                    
                    <div class="server-info-item">
                        <label class="form-label">服务器时间</label>
                        <div class="input">${data.server_time}</div>
                    </div>
                    
                    <div class="server-info-item">
                        <label class="form-label">今日访问量</label>
                        <div class="input">${data.today_visits || 0}</div>
                    </div>
                    
                    <div class="server-info-item">
                        <label class="form-label">今日独立访客</label>
                        <div class="input">${data.today_unique_visits || 0}</div>
                    </div>
                </div>
            `;
        }
        
        function renderOnlineUsers(data) {
            const container = document.getElementById('users-container');
            const agentsCount = data.online_agents_count || data.online_agents || 0;
            const customersCount = data.online_customers_count || data.online_customers || 0;
            
            // 清空容器，但保留标题
            container.innerHTML = '<h2 class="card-title">在线用户</h2>';
            
            let html = '';
            
            // 在线客服部分
            if (agentsCount > 0 && data.online_agents_list) {
                html += `
                    <div class="users-section mb-6">
                        <h3 class="form-label mb-4">在线客服 (${agentsCount} 人)</h3>
                        <div class="users-grid">
                `;
                
                data.online_agents_list.forEach(user => {
                    html += createUserCard(user, 'agent');
                });
                
                html += `
                        </div>
                    </div>
                `;
            }
            
            // 在线客户部分
            if (customersCount > 0 && data.online_customers_list) {
                html += `
                    <div class="users-section">
                        <h3 class="form-label mb-4">在线客户 (${customersCount} 人)</h3>
                        <div class="users-grid">
                `;
                
                data.online_customers_list.forEach(user => {
                    html += createUserCard(user, 'customer');
                });
                
                html += `
                        </div>
                    </div>
                `;
            }
            
            // 如果没有在线用户
            if (agentsCount === 0 && customersCount === 0) {
                html = `
                    <div class="empty-state">
                        <div class="icon">喜乐科技</div>
                        <div>暂无在线用户</div>
                    </div>
                `;
            }
            
            container.innerHTML += html;
        }
        
        function createUserCard(user, type) {
            const initials = user.username.substring(0, 2).toUpperCase();
            const lastHeartbeat = formatTime(user.last_heartbeat);
            
            return `
                <div class="user-card ${type}">
                    <div class="user-header">
                        <div class="user-avatar">${initials}</div>
                        <div class="user-info">
                            <div class="user-name">${escapeHtml(user.username)}</div>
                            <div class="user-type">${type === 'agent' ? '客服' : '客户'}</div>
                        </div>
                    </div>
                    <div class="user-details">
                        <div class="detail-item">
                            <span class="detail-label">状态</span>
                            <span class="detail-value">
                                <span class="status-indicator"></span>在线
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">最后心跳</span>
                            <span class="detail-value">${lastHeartbeat}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">窗口状态</span>
                            <span class="detail-value">${translateWindowStatus(user.window_status)}</span>
                        </div>
                        ${user.client_ip ? `
                        <div class="detail-item">
                            <span class="detail-label">IP 地址</span>
                            <span class="detail-value">${escapeHtml(user.client_ip)}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }
        
        function formatTime(datetime) {
            const date = new Date(datetime);
            const now = new Date();
            const diff = now - date;
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);
            
            if (minutes < 1) return '刚刚';
            if (minutes < 60) return `${minutes}分钟前`;
            if (hours < 24) return `${hours}小时前`;
            return `${days}天前`;
        }
        
        function translateWindowStatus(status) {
            const map = {
                'window_visible': '窗口可见',
                'window_hidden': '窗口隐藏',
                'window_closed': '窗口关闭'
            };
            return map[status] || status;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function getUsageClass(percentage) {
            if (percentage < 50) return 'low';
            if (percentage < 80) return 'medium';
            return 'high';
        }
        
        function updateLastUpdateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('zh-CN', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('last-update').textContent = '最后更新：' + timeString;
        }
        
        function showError(message) {
            const container = document.getElementById('stats-container');
            container.innerHTML = `
                <div class="error-card">
                    <div class="error-icon">⚠️</div>
                    <div class="stat-label" style="font-size: 1rem;">${message}</div>
                </div>
            `;
        }
        
        // 初始加载
        refreshData();
        
        // 自动刷新（每 5 秒）
        autoRefreshInterval = setInterval(refreshData, 5000);
        
        // 页面卸载时清除定时器
        window.addEventListener('beforeunload', () => {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        });
        
        // 页面加载动画
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '0';
            setTimeout(() => {
                document.body.style.transition = 'opacity 0.3s';
                document.body.style.opacity = '1';
            }, 10);
        });
    </script>
</body>
</html>