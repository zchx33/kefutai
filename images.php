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
    <title>图片资源管理 - 管理面板</title>
    <!-- 引入 Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
        
        /* 工具栏按钮样式 */
        .toolbar {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
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
        
        .btn-danger {
            background-color: var(--red-500);
        }
        
        .btn-danger:hover {
            background-color: var(--red-600);
        }
        
        .btn-icon {
            width: 1em;
            height: 1em;
        }
        
        /* 图片网格 */
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1.5rem;
        }
        
        /* 图片卡片 */
        .image-card {
            border-radius: 0.5rem;
            overflow: hidden;
            background: var(--white);
            border: 1px solid var(--gray-200);
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
        }
        
        .image-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .image-preview {
            width: 100%;
            height: 140px;
            object-fit: cover;
            display: block;
            background: var(--gray-100);
        }
        
        .image-info {
            padding: 1rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .image-name {
            font-size: 0.875rem;
            margin-bottom: 0.75rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--gray-700);
            font-weight: 500;
            text-align: center;
        }
        
        .image-actions {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            margin-top: auto;
        }
        
        .action-btn {
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
        }
        
        .preview-btn {
            background-color: var(--blue-500);
            color: var(--white);
        }
        
        .preview-btn:hover {
            background-color: var(--blue-600);
        }
        
        .delete-btn {
            background-color: var(--red-500);
            color: var(--white);
        }
        
        .delete-btn:hover {
            background-color: var(--red-600);
        }
        
        /* 预览模态框 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            max-width: 90%;
            max-height: 90%;
            position: relative;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .modal-image {
            max-width: 100%;
            max-height: 100%;
            display: block;
            margin: 0 auto;
        }
        
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
            background: rgba(0, 0, 0, 0.6);
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            background: rgba(0, 0, 0, 0.8);
        }
        
        /* 确认模态框 */
        .confirm-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1001;
            justify-content: center;
            align-items: center;
        }
        
        .confirm-modal-content {
            background: var(--white);
            border-radius: 0.5rem;
            padding: 1.5rem;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.1);
        }
        
        .confirm-modal-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .confirm-modal-icon {
            background: var(--red-500);
            color: var(--white);
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .confirm-modal-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .confirm-modal-message {
            color: var(--gray-700);
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }
        
        .confirm-modal-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }
        
        .confirm-modal-btn {
            padding: 0.5rem 1.25rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .confirm-modal-cancel {
            background-color: var(--gray-200);
            color: var(--gray-700);
        }
        
        .confirm-modal-cancel:hover {
            background-color: var(--gray-300);
        }
        
        .confirm-modal-confirm {
            background-color: var(--red-500);
            color: var(--white);
        }
        
        .confirm-modal-confirm:hover {
            background-color: var(--red-600);
        }
        
        /* 加载状态 */
        .loading {
            text-align: center;
            padding: 3rem;
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
        
        /* 空状态 */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray-500);
        }
        
        .empty-state .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* 通知样式 */
        .notification {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 1rem 1.5rem;
            border-radius: 0.375rem;
            color: var(--white);
            font-weight: 500;
            z-index: 1002;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            max-width: 300px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .notification.success {
            background-color: var(--green-500);
        }
        
        .notification.error {
            background-color: var(--red-500);
        }
        
        .notification.info {
            background-color: var(--blue-500);
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
                <h1 class="title">图片资源管理</h1>
                <div class="header-actions">
                    <!-- 工具栏按钮将在主内容区域显示 -->
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- 工具卡片 -->
            <div class="card">
                <h2 class="card-title">图片管理</h2>
                <div class="toolbar">
                    <button class="btn btn-primary" id="refreshBtn">
                        <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.311.311a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V2.929a.75.75 0 0 0-1.5 0v2.43l-.311-.311A7 7 0 0 0 3.239 8.188a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.311h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clip-rule="evenodd" />
                        </svg>
                        刷新列表
                    </button>
                    <button class="btn btn-danger" id="cleanBtn">
                        <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                        </svg>
                        一键清理
                    </button>
                </div>
            </div>

            <!-- 图片列表卡片 -->
            <div class="card">
                <h2 class="card-title">图片列表</h2>
                <div id="imageContainer" class="image-grid">
                    <div class="loading">
                        <div class="spinner"></div>
                        <div>正在加载图片...</div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- 图片预览模态框 -->
    <div id="previewModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" id="closeModal">&times;</span>
            <img id="modalImage" class="modal-image" src="" alt="预览图片">
        </div>
    </div>
    
    <!-- 删除确认模态框 -->
    <div id="confirmModal" class="confirm-modal">
        <div class="confirm-modal-content">
            <div class="confirm-modal-header">
                <div class="confirm-modal-icon">
                    <i class="bi-exclamation-triangle"></i>
                </div>
                <h3 class="confirm-modal-title">确认删除</h3>
            </div>
            <div class="confirm-modal-message" id="confirmModalMessage">
                确定要删除这张图片吗？此操作不可撤销。
            </div>
            <div class="confirm-modal-actions">
                <button class="confirm-modal-btn confirm-modal-cancel" id="confirmModalCancel">取消</button>
                <button class="confirm-modal-btn confirm-modal-confirm" id="confirmModalConfirm">确认删除</button>
            </div>
        </div>
    </div>

    <script>
        // 图片管理器类
        class ImageManager {
            constructor() {
                this.imageContainer = document.getElementById('imageContainer');
                this.previewModal = document.getElementById('previewModal');
                this.modalImage = document.getElementById('modalImage');
                this.closeModal = document.getElementById('closeModal');
                this.refreshBtn = document.getElementById('refreshBtn');
                this.cleanBtn = document.getElementById('cleanBtn');
                
                // 删除确认模态框元素
                this.confirmModal = document.getElementById('confirmModal');
                this.confirmModalMessage = document.getElementById('confirmModalMessage');
                this.confirmModalCancel = document.getElementById('confirmModalCancel');
                this.confirmModalConfirm = document.getElementById('confirmModalConfirm');
                
                this.currentDeleteImage = null; // 当前要删除的图片信息
                
                this.initEvents();
                this.loadImages();
                
                // 页面加载动画
                document.body.style.opacity = '0';
                setTimeout(() => {
                    document.body.style.transition = 'opacity 0.3s';
                    document.body.style.opacity = '1';
                }, 10);
            }
            
            // 初始化事件监听
            initEvents() {
                // 预览模态框事件
                this.closeModal.addEventListener('click', () => {
                    this.hidePreview();
                });
                
                this.previewModal.addEventListener('click', (e) => {
                    if (e.target === this.previewModal) {
                        this.hidePreview();
                    }
                });
                
                // 刷新和清理按钮事件
                this.refreshBtn.addEventListener('click', () => {
                    this.loadImages();
                });
                
                this.cleanBtn.addEventListener('click', () => {
                    this.cleanUnusedImages();
                });
                
                // ESC键关闭预览
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.hidePreview();
                        this.hideConfirmModal();
                    }
                });
                
                // 删除确认模态框事件
                this.confirmModalCancel.addEventListener('click', () => {
                    this.hideConfirmModal();
                });
                
                this.confirmModalConfirm.addEventListener('click', () => {
                    this.confirmDelete();
                });
                
                this.confirmModal.addEventListener('click', (e) => {
                    if (e.target === this.confirmModal) {
                        this.hideConfirmModal();
                    }
                });
            }
            
            // 加载图片列表
            async loadImages() {
                try {
                    this.imageContainer.innerHTML = '<div class="loading"><div class="spinner"></div><div>正在加载图片...</div></div>';
                    
                    const response = await fetch('/config/config_img.php');
                    const images = await response.json();
                    
                    this.renderImages(images);
                } catch (error) {
                    console.error('加载图片失败:', error);
                    this.imageContainer.innerHTML = '<div class="empty-state">加载失败，请刷新页面重试</div>';
                }
            }
            
            // 渲染图片列表
            renderImages(images) {
                if (!images || images.length === 0) {
                    this.imageContainer.innerHTML = '<div class="empty-state"><div class="icon">喜乐科技</div><div>暂无图片</div></div>';
                    return;
                }
                
                this.imageContainer.innerHTML = '';
                
                images.forEach(image => {
                    const imageCard = this.createImageCard(image);
                    this.imageContainer.appendChild(imageCard);
                });
            }
            
            // 创建图片卡片
            createImageCard(image) {
                const card = document.createElement('div');
                card.className = 'image-card';
                
                // 图片预览部分
                const img = document.createElement('img');
                img.className = 'image-preview';
                img.src = image.url;
                img.alt = image.name;
                img.loading = 'lazy';
                
                // 图片信息部分
                const info = document.createElement('div');
                info.className = 'image-info';
                
                const name = document.createElement('div');
                name.className = 'image-name';
                name.title = image.name;
                name.textContent = image.name;
                
                const actions = document.createElement('div');
                actions.className = 'image-actions';
                
                const previewBtn = document.createElement('button');
                previewBtn.className = 'action-btn preview-btn';
                previewBtn.innerHTML = '<i class="bi-eye"></i> 预览';
                previewBtn.addEventListener('click', () => {
                    this.showPreview(image.url);
                });
                
                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'action-btn delete-btn';
                deleteBtn.innerHTML = '<i class="bi-trash"></i> 删除';
                deleteBtn.addEventListener('click', () => {
                    this.showDeleteConfirm(image);
                });
                
                actions.appendChild(previewBtn);
                actions.appendChild(deleteBtn);
                
                info.appendChild(name);
                info.appendChild(actions);
                
                card.appendChild(img);
                card.appendChild(info);
                
                return card;
            }
            
            // 显示图片预览
            showPreview(imageUrl) {
                this.modalImage.src = imageUrl;
                this.previewModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
            
            // 隐藏图片预览
            hidePreview() {
                this.previewModal.style.display = 'none';
                document.body.style.overflow = 'auto';
                this.modalImage.src = '';
            }
            
            // 显示删除确认模态框
            showDeleteConfirm(image) {
                this.currentDeleteImage = image;
                this.confirmModalMessage.textContent = `确定要删除图片 "${image.name}" 吗？此操作不可撤销。`;
                this.confirmModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
            
            // 隐藏删除确认模态框
            hideConfirmModal() {
                this.confirmModal.style.display = 'none';
                document.body.style.overflow = 'auto';
                this.currentDeleteImage = null;
            }
            
            // 确认删除操作
            async confirmDelete() {
                if (!this.currentDeleteImage) return;
                
                try {
                    const response = await fetch('/config/config_img.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ path: this.currentDeleteImage.path })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.showNotification('图片删除成功', 'success');
                        this.loadImages(); // 刷新列表
                    } else {
                        this.showNotification('删除失败: ' + result.message, 'error');
                    }
                } catch (error) {
                    console.error('删除图片失败:', error);
                    this.showNotification('删除失败，请重试', 'error');
                } finally {
                    this.hideConfirmModal();
                }
            }
            
            // 一键清理未使用图片
            async cleanUnusedImages() {
                if (!confirm('这将扫描并删除系统中未使用的图片。确定要继续吗？')) {
                    return;
                }
                
                try {
                    this.cleanBtn.disabled = true;
                    this.cleanBtn.innerHTML = '<i class="bi-arrow-clockwise"></i> 清理中...';
                    
                    const response = await fetch('/config/config_img.php', {
                        method: 'POST'
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.showNotification(`清理完成！删除了 ${result.deletedCount} 个未使用的图片文件`, 'success');
                        this.loadImages(); // 刷新列表
                    } else {
                        this.showNotification('清理失败: ' + result.message, 'error');
                    }
                } catch (error) {
                    console.error('清理失败:', error);
                    this.showNotification('清理失败，请重试', 'error');
                } finally {
                    this.cleanBtn.disabled = false;
                    this.cleanBtn.innerHTML = '<i class="bi-trash"></i> 一键清理';
                }
            }
            
            // 显示通知
            showNotification(message, type = 'info') {
                // 创建通知元素
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                
                let icon = '';
                if (type === 'success') {
                    icon = '<i class="bi-check-circle"></i>';
                } else if (type === 'error') {
                    icon = '<i class="bi-exclamation-circle"></i>';
                } else {
                    icon = '<i class="bi-info-circle"></i>';
                }
                
                notification.innerHTML = icon + message;
                document.body.appendChild(notification);
                
                // 显示动画
                setTimeout(() => {
                    notification.style.transform = 'translateX(0)';
                }, 100);
                
                // 3秒后自动消失
                setTimeout(() => {
                    notification.style.transform = 'translateX(400px)';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }, 3000);
            }
        }
        
        // 页面加载完成后初始化
        document.addEventListener('DOMContentLoaded', () => {
            new ImageManager();
        });
    </script>
</body>
</html>