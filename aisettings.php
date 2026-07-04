<?php 
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();
checkAdmin();
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI配置管理</title>
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
        
        .btn-secondary {
            background-color: var(--gray-200);
            color: var(--gray-800);
        }
        
        .btn-secondary:hover {
            background-color: var(--gray-300);
        }
        
        /* Row layout */
        .form-row {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .form-row .input {
            flex: 1 1 0%;
        }
        
        /* Select */
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
        
        /* Password Toggle */
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        
        /* Test Area */
        .test-area {
            background-color: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 0.375rem;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .test-area-title {
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }
        
        .test-input {
            width: 100%;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            color: var(--gray-900);
            font-size: 0.875rem;
            min-height: 5rem;
            resize: vertical;
            font-family: 'Noto Sans SC', sans-serif;
            margin-bottom: 0.75rem;
        }
        
        .test-input:focus {
            outline: none;
            border-color: transparent;
            box-shadow: 0 0 0 2px var(--blue-500);
        }
        
        .test-response {
            background-color: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 0.375rem;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            color: var(--gray-700);
            margin-top: 0.75rem;
            min-height: 4rem;
        }
        
        .test-response.success {
            border-color: var(--green-500);
            background-color: rgba(16, 185, 129, 0.05);
        }
        
        .test-response.error {
            border-color: var(--red-500);
            background-color: rgba(239, 68, 68, 0.05);
        }
        
        /* Loading */
        .loading {
            display: none;
            text-align: center;
            padding: 0.75rem;
            color: var(--gray-500);
            font-size: 0.875rem;
        }
        
        /* Actions */
        .actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        
        .actions .btn {
            flex: 1;
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
        
        /* Responsive */
        @media (min-width: 640px) {
            .form-row {
                flex-direction: row;
                gap: 1rem;
            }
            
            .form-row .input {
                flex: 1;
            }
            
            .actions {
                justify-content: flex-end;
            }
            
            .actions .btn {
                flex: 0 1 auto;
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
        
        .w-full {
            width: 100%;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
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
                <h1 class="title">AI模块</h1>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- 消息显示 -->
            <div id="message" class="message" style="display: none;"></div>
            
            <!-- 加载状态 -->
            <div id="loading" class="loading" style="display: none;">
                保存中...
            </div>
            
            <!-- API密钥配置 -->
            <section class="card">
                <h2 class="card-title">API密钥配置</h2>
                
                <div class="form-group mb-6">
                    <label for="api_key" class="form-label">DeepSeek API密钥</label>
                    <div class="password-container">
                        <input type="password" id="api_key" name="api_key" class="input" 
                               placeholder="请输入 DeepSeek API Key" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('api_key')">显示</button>
                    </div>
                    <p class="form-hint">不推荐使用,官方限制严重</p>
                </div>
                
                <div class="form-group mb-6">
                    <label for="qwen_api_key" class="form-label">通义千问 API密钥</label>
                    <div class="password-container">
                        <input type="password" id="qwen_api_key" name="qwen_api_key" class="input" 
                               placeholder="请输入通义千问 API Key">
                        <button type="button" class="password-toggle" onclick="togglePassword('qwen_api_key')">显示</button>
                    </div>
                    <p class="form-hint">推荐使用,具体获取请移步阿里云</p>
                </div>
            </section>
            
            <!-- 模型参数配置 -->
            <section class="card">
                <h2 class="card-title">模型参数配置</h2>
                
                <div class="form-group mb-6">
                    <label for="model" class="form-label">主模型选择</label>
                    <select id="model" name="model" class="select">
                        <option value="deepseek-v3.2">DeepSeek-V3.2</option>
                        <option value="qwen-plus">Qwen-Plus</option>
                    </select>
                    <p class="form-hint">推荐选择【Qwen-Plus】</p>
                </div>
                
                <div class="form-row mb-6">
                    <div class="form-group">
                        <label for="max_tokens" class="form-label">最大回复长度</label>
                        <input type="number" id="max_tokens" name="max_tokens" class="input" 
                               min="50" max="1000" value="120" required>
                        <p class="form-hint">设置 AI 回复的最大长度（建议 80-200）</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="temperature" class="form-label">温度参数</label>
                        <input type="number" id="temperature" name="temperature" class="input" 
                               min="0" max="2" step="0.1" value="0.6" required>
                        <p class="form-hint">控制回复的随机性（0-2，值越大越随机）</p>
                    </div>
                </div>
                
                <div class="form-group mb-6">
                    <label for="max_history" class="form-label">最大历史记录</label>
                    <input type="number" id="max_history" name="max_history" class="input" 
                           min="2" max="20" value="8" required>
                    <p class="form-hint">每次对话保留的历史消息数量</p>
                </div>
                
                <div class="actions">
                    <button type="button" class="btn btn-primary" onclick="saveConfig()">
                        保存配置
                    </button>
                </div>
            </section>
            
            <!-- AI回复测试 -->
            <section class="test-area">
                <h3 class="test-area-title">AI回复测试</h3>
                
                <textarea class="test-input" id="testInput" placeholder="输入测试消息...">你好，我想了解游戏账号交易流程</textarea>
                
                <button type="button" class="btn btn-secondary" onclick="testAI()" style="width: 100%;">
                    测试 AI 回复
                </button>
                
                <div class="test-response" id="testResponse">
                    测试回复将显示在这里...
                </div>
            </section>
        </main>
    </div>
    
    <script>
        // 页面加载时获取当前配置
        document.addEventListener('DOMContentLoaded', function() {
            loadConfig();
        });
        
        // 切换密码显示
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                button.textContent = '隐藏';
            } else {
                field.type = 'password';
                button.textContent = '显示';
            }
        }
        
        // 显示消息
        function showMessage(message, type = 'info', duration = 3000) {
            const messageEl = document.getElementById('message');
            messageEl.textContent = message;
            messageEl.className = `message message-${type}`;
            messageEl.style.display = 'block';
            
            setTimeout(() => {
                messageEl.style.opacity = '0';
                messageEl.style.transition = 'opacity 0.3s ease-out';
                
                setTimeout(() => {
                    messageEl.style.display = 'none';
                    messageEl.style.opacity = '1';
                }, 300);
            }, duration);
        }
        
        // 加载当前配置
        async function loadConfig() {
            try {
                const response = await axios.get('/api/ai/XE?action=get_config');
                if (response.data.success) {
                    const config = response.data.config;
                    
                    // 填充表单
                    document.getElementById('api_key').value = config.api_key || '';
                    document.getElementById('qwen_api_key').value = config.qwen_api_key || '';
                    document.getElementById('model').value = config.model || 'deepseek-v3.2';
                    document.getElementById('max_tokens').value = config.max_tokens || 120;
                    document.getElementById('temperature').value = config.temperature || 0.6;
                    document.getElementById('max_history').value = config.max_history || 8;
                    
                    showMessage('配置加载成功', 'success');
                } else {
                    showMessage('加载配置失败: ' + response.data.message, 'error');
                }
            } catch (error) {
                console.error('加载配置失败:', error);
                showMessage('加载配置失败: 请检查网络连接', 'error');
            }
        }
        
        // 保存配置
        async function saveConfig() {
            const formData = {
                api_key: document.getElementById('api_key').value.trim(),
                qwen_api_key: document.getElementById('qwen_api_key').value.trim(),
                model: document.getElementById('model').value,
                max_tokens: parseInt(document.getElementById('max_tokens').value),
                temperature: parseFloat(document.getElementById('temperature').value),
                max_history: parseInt(document.getElementById('max_history').value)
            };
            
            // 验证必填字段
            if (!formData.api_key) {
                showMessage('DeepSeek API Key 是必填项', 'error');
                return;
            }
            
            const loadingEl = document.getElementById('loading');
            loadingEl.style.display = 'block';
            
            try {
                const response = await axios.post('/api/ai/XE?action=save_config', formData);
                loadingEl.style.display = 'none';
                
                if (response.data.success) {
                    showMessage('配置已成功保存', 'success');
                } else {
                    showMessage('保存失败: ' + response.data.message, 'error');
                }
            } catch (error) {
                loadingEl.style.display = 'none';
                console.error('保存配置失败:', error);
                showMessage('保存失败: 请检查网络连接', 'error');
            }
        }
        
        // 测试 AI 回复
        async function testAI() {
            const testInput = document.getElementById('testInput').value.trim();
            const testResponse = document.getElementById('testResponse');
            
            if (!testInput) {
                showMessage('请输入测试消息', 'error');
                return;
            }
            
            testResponse.textContent = '正在生成回复...';
            testResponse.className = 'test-response';
            
            try {
                const response = await axios.post('/api/ai/XE?action=test_reply', {
                    message: testInput
                });
                
                if (response.data.success) {
                    testResponse.innerHTML = `
                        <strong>回复内容:</strong> ${response.data.reply}<br>
                        <strong>使用模型:</strong> ${response.data.model}<br>
                        <strong>消耗 Token:</strong> ${response.data.usage?.total_tokens || '未知'}
                    `;
                    testResponse.className = 'test-response success';
                    showMessage('AI 回复测试完成', 'success');
                } else {
                    testResponse.textContent = '测试失败: ' + response.data.message;
                    testResponse.className = 'test-response error';
                    showMessage('测试失败: ' + response.data.message, 'error');
                }
            } catch (error) {
                testResponse.textContent = '测试异常: ' + error.message;
                testResponse.className = 'test-response error';
                showMessage('测试异常: ' + error.message, 'error');
            }
        }
    </script>
</body>
</html>