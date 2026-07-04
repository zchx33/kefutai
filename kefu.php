<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
session_start();
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    http_response_code(401);
    exit('未登录');
}
$currentAgent = $_SESSION['username'];
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
    <link rel="stylesheet" href="/assets/top_bar.css">
    <link rel="stylesheet" href="/assets/xekefu.css">
    <link rel="stylesheet" href="/assets/bootstrap-icons.css">

    <!-- 添加分享功能所需的库 -->
    <script src="/assets/qrcode.min.js"></script>
    <script src="/assets/html2canvas.min.js"></script>
    <style>
    body{
        padding: 0;
    }
        /* 新通知样式 - 从顶部进入 */
        .new-user-toast {
            position: fixed;
            top: 10px;
            left: 10px;
            right: 10px;
            background: #4caf50;
            border-radius: 8px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            z-index: 99999;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
            cursor: pointer;
            will-change: transform,opacity;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            max-width: calc(100% - 20px);
            margin: 0 auto;
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
                transform: translateY(-100px);
                opacity: 0;
            }
            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes notification-slide-out {
            0% {
                transform: translateY(0);
                opacity: 1;
            }
            100% {
                transform: translateY(-100px);
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
        
        /* 隐藏状态 */
        .toast-hidden {
            display: none;
        }
        
        /* 模态框覆盖层 - 改为从底部滑出的抽屉式 */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: flex-end;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal-overlay.show {
            display: flex;
            opacity: 1;
        }
        
        /* 模态框内容 - 改为从底部滑出的抽屉 */
        .modal-content {
            background-color: white;
            border-radius: 16px 16px 0 0;
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
        }
        
        .modal-overlay.show .modal-content {
            transform: translateY(0);
        }
        
        .modal-header {
    padding: 10px;
    border-bottom: 1px solid #e8e8e8;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

        .upload-container {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .upload-container.dragover {
            border-color: #007bff;
            background-color: #e3f2fd;
        }

        .upload-area {
            cursor: pointer;
        }

        .upload-area i {
            font-size: 2rem;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .upload-hint {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .preview-container {
            text-align: center;
        }

        .preview-image {
            max-width: 150px;
            max-height: 150px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .upload-progress {
            width: 100%;
            height: 4px;
            background: #f1f1f1;
            border-radius: 2px;
            margin-top: 10px;
            overflow: hidden;
            display: none;
        }

        .progress-bar {
            height: 100%;
            background: #007bff;
            width: 0%;
            transition: width 0.3s ease;
        }
        .container {
            padding: 20px;
        }
    #addServicePageBtn {
      background-color: #1890ff;
    color: white;
    border: none;
    border-radius: 6px;
    padding: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(24, 144, 255, 0.2);
    margin-left: auto;
    margin-right: 10px;
}
    </style>
</head>
<body>
    <div class="top-header">
	<a href="javascript:void(0)" onclick="window.parent.postMessage('closeModal', '*')" style="display: inline-flex; align-items: center; text-decoration: none; color: inherit;">
		<svg t="1768667202128" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4699" width="18" height="18">
			<path
				d="M285.8112 565.76a56.4864 56.4864 0 0 0 39.04-16.3712l452.7744-453.76A56.5248 56.5248 0 0 0 778.24 16.64a54.8992 54.8992 0 0 0-78.08-0.5632L247.3344 469.76a56.5248 56.5248 0 0 0-0.5504 79.0144 50.048 50.048 0 0 0 39.0272 16.9344zM733.568 1024a56.1664 56.1664 0 0 0 39.6032-95.3856l-448.32-458.24a54.912 54.912 0 0 0-78.08-0.5632 56.5248 56.5248 0 0 0-0.5632 79.0144l448.32 458.24A53.76 53.76 0 0 0 733.568 1024z m0 0"
				fill="#333333" p-id="4700"></path>
		</svg>
	</a>
	<div style="border: 14px solid transparent;">自定义客服管理</div>

        <button class="add-btn" id="addServicePageBtn"> 创建新页面 </button>
</div>

<!-- 防红配置状态显示 -->
<div class="anti-red-status-bar" id="antiRedStatusBar" style="display: none; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 20px; margin: 10px 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
    <div style="display: flex; align-items: center; gap: 10px;">
        <i class="bi bi-shield-check" style="font-size: 20px;"></i>
        <div>
            <div style="font-weight: 600; font-size: 14px;">防红配置已启用</div>
            <div style="font-size: 12px; opacity: 0.9; margin-top: 2px;">
                当前配置接口：<span id="currentAntiRedDomain" style="font-weight: 500;"></span>
            </div>
        </div>
    </div>
</div>
    
    <div class="container">

    <div class="cards-grid" id="servicePagesContainer">
        <!-- JS动态渲染 -->
    </div>

    <!-- 添加/编辑客服页面模态框 -->
    <div class="modal-overlay" id="servicePageModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">添加客服页面</h3>
            </div>
            <form id="servicePageForm">
                <input type="hidden" id="page_id" name="page_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="page_title">页面标题【客服名称】</label>
                        <input type="text" class="form-control" id="page_title" name="page_title" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="company_name">公司名称【顶部名称】</label>
                        <input type="text" class="form-control" id="company_name" name="company_name">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="company_subtitle">公司副标题【公司下方】</label>
                        <input type="text" class="form-control" id="company_subtitle" name="company_subtitle">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="badge_text">认证徽章文字</label>
                        <input type="text" class="form-control" id="badge_text" name="badge_text">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="service_hours">服务时间</label>
                        <input type="text" class="form-control" id="service_hours" name="service_hours" placeholder="例如：9:00-18:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="top_badge_1">顶部徽章1</label>
                        <input type="text" class="form-control" id="top_badge_1" name="top_badge_1">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="top_badge_2">顶部徽章2</label>
                        <input type="text" class="form-control" id="top_badge_2" name="top_badge_2">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="welcome_message">欢迎语</label>
                        <textarea class="form-control" id="welcome_message" name="welcome_message" rows="3"></textarea>
                    </div>
                    <!-- 修改后的头像上传部分 -->
                    <div class="form-group">
                        <label class="form-label" for="avatar_upload">头像上传</label>
                        <div class="upload-container">
                            <input type="file" class="form-control" id="avatar_upload" name="avatar_upload" accept="image/*" style="display: none;">
                            <div class="upload-area" id="uploadArea">
                                <i class="bi bi-cloud-upload"></i>
                                <p>点击选择图片或拖拽到此区域</p>
                                <span class="upload-hint">支持 JPG, PNG 格式，最大 2MB</span>
                            </div>
                            <div class="preview-container" id="previewContainer" style="display: none;">
                                <img id="avatar_preview" src="#" alt="头像预览" class="preview-image">
                                <button type="button" class="btn btn-sm btn-outline-danger mt-2" id="removeImageBtn">移除图片</button>
                            </div>
                        </div>
                        <input type="hidden" id="avatar_url" name="avatar_url">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="poster_entry_text">海报入口文案【分享图文案】</label>
                        <input type="text" class="form-control" id="poster_entry_text" name="poster_entry_text">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="status">状态 *</label>
                        <select class="form-control form-select" id="status" name="status" required>
                            <option value="active">启用</option>
                            <option value="inactive">禁用</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancelBtn">取消</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <span id="submitText">添加客服页面</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 删除确认模态框 -->
    <div class="modal-overlay" id="deleteConfirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">确认删除</h3>
            </div>
            <div class="modal-body">
                <p>确定要删除客服页面 "<strong id="deletePageTitle"></strong>" 吗？此操作不可恢复。</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="cancelDeleteBtn">取消</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">确认删除</button>
            </div>
        </div>
    </div>

    <!-- 分享选择模态框 -->
    <div class="modal-overlay" id="shareOptionsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">分享选项</h3>
            </div>
            <div class="modal-body">
                <div class="share-options">
                    <button class="share-option-btn" id="copyLinkBtn">
                        <i class="bi bi-link-45deg"></i>
                        <span>复制链接</span>
                    </button>
                    <button class="share-option-btn" id="shareImageBtn">
                        <i class="bi bi-image"></i>
                        <span>分享图</span>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="cancelShareBtn">取消</button>
            </div>
        </div>
    </div>

    <!-- 分享图模态框 -->
    <div class="modal-overlay" id="shareImageModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title">分享图</h3>
            </div>
            <div class="modal-body">
                <div class="qrPoster" id="shareImageContainer">
                    <div class="qrPosterInner">
                        <div class="qrPosterTop">
                            <img class="qrPosterAvatar" id="posterAvatar" src="http://q1.qlogo.cn/g?b=qq&nk=113377210&s=100" alt="头像">
                            <div class="qrPosterName" id="posterName">客服页面</div>

                            <div class="qrPosterBadges">
                                <span class="qrPosterTag" id="posterTag">企业客服入口</span>
                                <span class="qrBadge hours" id="posterHours" style="display: inline-flex;">09:00-23:00</span>
                            </div>

                            <div class="qrPosterHint" id="posterHint">使用浏览器扫一扫进入客服页面</div>
                        </div>

                        <div class="qrDivider"></div>

                        <div class="qrPosterMid">
                            <div class="qrBox">
                                <div id="shareQrCode"></div>
                                <img class="qrAvatar" id="qrAvatar" src="http://q1.qlogo.cn/g?b=qq&nk=113377210&s=100" alt="二维码中间头像">
                            </div>

                            <div class="qrPosterScan">建议使用浏览器"扫一扫"识别（更稳定）</div>
                        </div>

                        <div class="qrPosterFoot">提示：若扫码失败，可返回选择"复制链接"方式打开。</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="cancelImageBtn">关闭</button>
                <button type="button" class="btn btn-primary" id="downloadImageBtn">
                    <i class="bi bi-download"></i> 保存为图片
                </button>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '/api/kefu-manage';
        const CURRENT_AGENT = '<?php echo $currentAgent; ?>';

        // 模态框控制
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // 分享功能变量
        let currentShareData = null;
        let userAntiRedConfig = null;
        let siteUrlConfig = null;

        // 动态渲染页面列表
        function renderServicePages(pages) {
            const container = document.getElementById('servicePagesContainer');
            if (!pages || pages.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="bi bi-chat-dots"></i><p>暂无客服页面</p></div>';
                return;
            }
            
            container.innerHTML = pages.map(page => {
                const isActive = page.XEmsg_status === 'active';
                const avatarUrl = page.XEmsg_avatar_url || 'http://q1.qlogo.cn/g?b=qq&nk=113377210&s=100';
                return `
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">${escapeHtml(page.XEmsg_page_title)}</div>
                            <div class="card-meta">创建于 ${page.XEmsg_created_at}</div>
                        </div>
                        <div class="card-id">ID:${page.XEmsg_id}</div>
                    </div>
                    <div class="card-content">
                        <div class="card-field">
                            <span class="field-label">状态</span>
                            <span class="status-badge ${isActive ? 'status-active' : 'status-inactive'}">
                                ${isActive ? '可访问' : '禁用'}
                            </span>
                        </div>
                        <div class="card-field">
                            <span class="field-label">公司名称</span>
                            <span class="field-value">${escapeHtml(page.XEmsg_company_name || '未设置')}</span>
                        </div>
                        <div class="card-field">
                            <span class="field-label">公司副标题</span>
                            <span class="field-value">${escapeHtml(page.XEmsg_company_subtitle || '未设置')}</span>
                        </div>
                        <div class="card-field">
                            <span class="field-label">分享图入口文案</span>
                            <span class="field-value">${escapeHtml(page.XEmsg_poster_entry_text || '未设置')}</span>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button class="btn btn-outline edit-page" data-id="${page.XEmsg_id}">
                            <i class="bi bi-pencil"></i> 编辑
                        </button>
                        <button class="btn btn-warning toggle-status" data-id="${page.XEmsg_id}" data-status="${page.XEmsg_status}">
                            <i class="bi bi-power"></i> ${isActive ? '禁用' : '启用'}
                        </button>
                        <button class="btn btn-primary share-page" 
                            data-id="${page.XEmsg_id}"
                            data-title="${escapeHtml(page.XEmsg_page_title)}"
                            data-code="${page.XEmsg_page_code}"
                            data-param="${page.XEmsg_share_param || ''}"
                            data-username="${CURRENT_AGENT}"
                            data-avatar="${escapeHtml(avatarUrl)}"
                            data-service-hours="${escapeHtml(page.XEmsg_service_hours || '未设置')}"
                            data-poster-entry="${escapeHtml(page.XEmsg_poster_entry_text || '企业客服入口')}">
                            <i class="bi bi-share"></i> 分享
                        </button>
                        <button class="btn btn-danger delete-page" data-id="${page.XEmsg_id}" data-title="${escapeHtml(page.XEmsg_page_title)}">
                            <i class="bi bi-trash"></i> 删除
                        </button>
                    </div>
                </div>`;
            }).join('');
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // 加载页面列表
        async function loadServicePages() {
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({action: 'list'})
                });
                const result = await response.json();
                if (result.success) {
                    renderServicePages(result.data);
                }
            } catch (error) {
                console.error('加载页面列表失败:', error);
            }
        }

        // 加载防红配置
        async function loadAntiRedConfig() {
            try {
                const response = await fetch('/config/domain_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({action: 'get_user_anti_red_config'})
                });
                
                const result = await response.json();
                
                if (result.success && result.config) {
                    userAntiRedConfig = result.config;
                    
                    const statusBar = document.getElementById('antiRedStatusBar');
                    const domainSpan = document.getElementById('currentAntiRedDomain');
                    
                    if (userAntiRedConfig.apply_status === 'on' && userAntiRedConfig.applied_domain) {
                        domainSpan.textContent = userAntiRedConfig.applied_domain;
                        statusBar.style.display = 'block';
                    } else {
                        statusBar.style.display = 'none';
                    }
                    
                    return userAntiRedConfig;
                }
            } catch (error) {
                console.error('加载防红配置失败:', error);
            }
            return null;
        }

        // 生成防红链接
        function generateAntiRedUrl(originalUrl) {
            if (!userAntiRedConfig || userAntiRedConfig.apply_status !== 'on' || !userAntiRedConfig.api_url) {
                return originalUrl;
            }
            
            try {
                let apiUrl = userAntiRedConfig.api_url.replace(/\/+$/, '');
                const encodedUrl = btoa(originalUrl);
                return apiUrl + encodedUrl;
            } catch (error) {
                console.error('生成防红链接失败:', error);
                return originalUrl;
            }
        }

        // 加载源站URL配置
        async function loadSiteUrlConfig() {
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({action: 'get_site_url_config'})
                });
                const result = await response.json();
                if (result.success && result.config) {
                    siteUrlConfig = result.config;
                }
            } catch (error) {
                console.error('加载源站URL配置失败:', error);
            }
        }

        // 生成最终链接（防红 > 源站URL > 原始）
        function generateFinalUrl(originalUrl) {
            if (userAntiRedConfig && userAntiRedConfig.apply_status === 'on' && userAntiRedConfig.api_url) {
                try {
                    let apiUrl = userAntiRedConfig.api_url.replace(/\/+$/, '');
                    const encodedUrl = btoa(originalUrl);
                    return apiUrl + encodedUrl;
                } catch (error) {
                    console.error('生成防红链接失败:', error);
                    return originalUrl;
                }
            } else if (siteUrlConfig && siteUrlConfig.site_url_enabled && siteUrlConfig.site_url) {
                try {
                    return siteUrlConfig.site_url + btoa(unescape(encodeURIComponent(originalUrl)));
                } catch (error) {
                    console.error('生成源站链接失败:', error);
                    return originalUrl;
                }
            }
            return originalUrl;
        }

        // 页面加载完成后初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 加载数据
            loadServicePages();
            loadAntiRedConfig();
            loadSiteUrlConfig();
            
            // 点击模态框外部关闭
            document.querySelectorAll('.modal-overlay').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal(this.id);
                    }
                });
            });
            
            // 文件上传相关功能
            const uploadInput = document.getElementById('avatar_upload');
            const uploadArea = document.getElementById('uploadArea');
            const previewContainer = document.getElementById('previewContainer');
            const avatarPreview = document.getElementById('avatar_preview');
            const removeImageBtn = document.getElementById('removeImageBtn');
            const avatarUrlInput = document.getElementById('avatar_url');
            const uploadProgress = document.createElement('div');
            uploadProgress.className = 'upload-progress';
            const progressBar = document.createElement('div');
            progressBar.className = 'progress-bar';
            uploadProgress.appendChild(progressBar);
            uploadArea.parentNode.appendChild(uploadProgress);

            uploadArea.addEventListener('click', () => uploadInput.click());

            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.parentNode.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', (e) => {
                e.preventDefault();
                uploadArea.parentNode.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.parentNode.classList.remove('dragover');
                if (e.dataTransfer.files.length > 0) {
                    handleFileSelect(e.dataTransfer.files[0]);
                }
            });

            uploadInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFileSelect(e.target.files[0]);
                }
            });

            removeImageBtn.addEventListener('click', (e) => {
                e.preventDefault();
                resetUpload();
            });

            function handleFileSelect(file) {
                if (!file.type.startsWith('image/')) {
                    showAlert('请选择有效的图片文件', 'danger');
                    return;
                }

                if (file.size > 2 * 1024 * 1024) {
                    showAlert('图片大小不能超过2MB', 'danger');
                    return;
                }

                const reader = new FileReader();
                reader.onload = (e) => {
                    avatarPreview.src = e.target.result;
                    uploadArea.style.display = 'none';
                    previewContainer.style.display = 'block';
                    uploadFile(file);
                };
                reader.readAsDataURL(file);
            }

            async function uploadFile(file) {
                const formData = new FormData();
                formData.append('avatar', file);
                formData.append('action', 'upload_avatar');

                try {
                    uploadProgress.style.display = 'block';
                    
                    const response = await fetch(API_BASE, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        avatarUrlInput.value = result.filePath;
                        showAlert('图片上传成功', 'success');
                    } else {
                        showAlert(result.message, 'danger');
                        resetUpload();
                    }
                } catch (error) {
                    showAlert('上传失败：' + error.message, 'danger');
                    resetUpload();
                } finally {
                    uploadProgress.style.display = 'none';
                    progressBar.style.width = '0%';
                }
            }

            function resetUpload() {
                uploadInput.value = '';
                avatarPreview.src = '#';
                uploadArea.style.display = 'block';
                previewContainer.style.display = 'none';
                avatarUrlInput.value = '';
            }

            window.loadExistingAvatar = function(avatarUrl) {
                if (avatarUrl) {
                    avatarPreview.src = avatarUrl;
                    avatarUrlInput.value = avatarUrl;
                    uploadArea.style.display = 'none';
                    previewContainer.style.display = 'block';
                }
            };

            document.getElementById('addServicePageBtn').addEventListener('click', () => {
                resetUpload();
                openModal('servicePageModal');
            });
            
            document.getElementById('cancelBtn').addEventListener('click', () => closeModal('servicePageModal'));
            document.getElementById('cancelDeleteBtn').addEventListener('click', () => closeModal('deleteConfirmModal'));

            // 事件委托处理动态元素
            document.getElementById('servicePagesContainer').addEventListener('click', function(e) {
                const target = e.target.closest('button');
                if (!target) return;

                if (target.classList.contains('edit-page')) {
                    editServicePage(target.dataset.id);
                } else if (target.classList.contains('toggle-status')) {
                    toggleServicePageStatus(target.dataset.id, target.dataset.status);
                } else if (target.classList.contains('delete-page')) {
                    showDeleteConfirm(target.dataset.id, target.dataset.title);
                } else if (target.classList.contains('share-page')) {
                    openShareOptions(
                        target.dataset.id, 
                        target.dataset.title, 
                        target.dataset.code, 
                        target.dataset.param,
                        target.dataset.username,
                        target.dataset.avatar,
                        target.dataset.serviceHours
                    );
                }
            });

            document.getElementById('servicePageForm').addEventListener('submit', handleFormSubmit);

            document.getElementById('cancelShareBtn').addEventListener('click', () => closeModal('shareOptionsModal'));
            document.getElementById('cancelImageBtn').addEventListener('click', () => closeModal('shareImageModal'));
            
            document.getElementById('copyLinkBtn').addEventListener('click', copyPageLink);
            document.getElementById('shareImageBtn').addEventListener('click', showShareImage);
            document.getElementById('downloadImageBtn').addEventListener('click', downloadShareImage);
        });

        // 编辑客服页面
        async function editServicePage(pageId) {
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({action: 'get_page_details', page_id: pageId})
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const page = result.data;
                    document.getElementById('modalTitle').textContent = '编辑客服页面';
                    document.getElementById('page_id').value = page.XEmsg_id;
                    document.getElementById('page_title').value = page.XEmsg_page_title;
                    document.getElementById('company_name').value = page.XEmsg_company_name || '';
                    document.getElementById('company_subtitle').value = page.XEmsg_company_subtitle || '';
                    document.getElementById('badge_text').value = page.XEmsg_badge_text || '';
                    document.getElementById('service_hours').value = page.XEmsg_service_hours || '';
                    document.getElementById('top_badge_1').value = page.XEmsg_top_badge_1 || '';
                    document.getElementById('top_badge_2').value = page.XEmsg_top_badge_2 || '';
                    document.getElementById('welcome_message').value = page.XEmsg_welcome_message || '';
                    
                    if (page.XEmsg_avatar_url) {
                        loadExistingAvatar(page.XEmsg_avatar_url);
                    }
                    
                    document.getElementById('poster_entry_text').value = page.XEmsg_poster_entry_text || '';
                    document.getElementById('status').value = page.XEmsg_status;
                    document.getElementById('submitText').textContent = '更新客服页面';
                    
                    openModal('servicePageModal');
                } else {
                    showAlert(result.message, 'danger');
                }
            } catch (error) {
                showAlert('加载页面信息失败：' + error, 'danger');
            }
        }

        // 切换页面状态
        async function toggleServicePageStatus(pageId, currentStatus) {
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({action: 'toggle_page_status', page_id: pageId})
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('页面状态已更新', 'success');
                    loadServicePages();
                } else {
                    showAlert(result.message, 'danger');
                }
            } catch (error) {
                showAlert('状态更新失败：' + error, 'danger');
            }
        }

        // 显示删除确认
        function showDeleteConfirm(pageId, pageTitle) {
            document.getElementById('deletePageTitle').textContent = pageTitle;
            openModal('deleteConfirmModal');
            
            document.getElementById('confirmDeleteBtn').onclick = () => deleteServicePage(pageId);
        }

        // 删除客服页面
        async function deleteServicePage(pageId) {
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({action: 'delete_service_page', page_id: pageId})
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('客服页面已删除', 'success');
                    closeModal('deleteConfirmModal');
                    loadServicePages();
                } else {
                    showAlert(result.message, 'danger');
                }
            } catch (error) {
                showAlert('删除失败：' + error, 'danger');
            }
        }

        // 生成页面 URL
        async function generatePageUrl(pageCode, shareParam, username) {
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'generate_share_url',
                        page_code: pageCode,
                        username: username
                    })
                });
                
                const result = await response.json();
                
                if (result.success && result.url) {
                    return result.url;
                } else {
                    const baseUrl = `${window.location.origin}/XeKefu`;
                    const characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
                    let randomString = '';
                    for (let i = 0; i < 6; i++) {
                        randomString += characters[Math.floor(Math.random() * characters.length)];
                    }
                    const sessionId = `a${randomString}z-p${username || 'default'}s`;
                    const xedataToken = md5(Date.now() + Math.random());
                    return generateFinalUrl(`${baseUrl}?code=${pageCode}&id=${sessionId}&XEDATA=${xedataToken}`);
                }
            } catch (error) {
                console.error('获取分享链接失败:', error);
                const baseUrl = `${window.location.origin}/XeKefu`;
                const characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
                let randomString = '';
                for (let i = 0; i < 6; i++) {
                    randomString += characters[Math.floor(Math.random() * characters.length)];
                }
                const sessionId = `a${randomString}z-p${username || 'default'}s`;
                const xedataToken = md5(Date.now() + Math.random());
                return generateFinalUrl(`${baseUrl}?code=${pageCode}&id=${sessionId}&XEDATA=${xedataToken}`);
            }
        }
        
        function md5(str) {
            const hash = (str) => {
                let h = 0x811c9dc5;
                for (let i = 0; i < str.length; i++) {
                    h ^= str.charCodeAt(i);
                    h += (h << 1) + (h << 4) + (h << 7) + (h << 8) + (h << 24);
                }
                return h >>> 0;
            };
            return hash(str).toString(16).padStart(32, '0');
        }

        // 打开分享选项
        async function openShareOptions(pageId, pageTitle, pageCode, shareParam, username, avatarUrl, serviceHours) {
            const shareButton = document.querySelector(`.share-page[data-id="${pageId}"]`);
            const posterEntryText = shareButton.dataset.posterEntry || '企业客服入口';
            
            const pageUrl = await generatePageUrl(pageCode, shareParam, username);
            
            currentShareData = {
                pageId: pageId,
                pageTitle: pageTitle,
                pageCode: pageCode,
                shareParam: shareParam,
                username: username,
                posterEntryText: posterEntryText,
                avatarUrl: avatarUrl || 'http://q1.qlogo.cn/g?b=qq&nk=113377210&s=100',
                serviceHours: serviceHours || '9:00-18:00',
                pageUrl: pageUrl
            };
            
            openModal('shareOptionsModal');
        }

        // 复制页面链接
        async function copyPageLink() {
            if (!currentShareData) return;
            
            try {
                const textArea = document.createElement('textarea');
                textArea.value = currentShareData.pageUrl;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                const success = document.execCommand('copy');
                document.body.removeChild(textArea);
                
                if (success) {
                    showAlert('页面链接已复制到剪贴板', 'success');
                } else {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        await navigator.clipboard.writeText(currentShareData.pageUrl);
                        showAlert('页面链接已复制到剪贴板', 'success');
                    } else {
                        showAlert('复制失败，请手动复制链接', 'danger');
                    }
                }
                closeModal('shareOptionsModal');
            } catch (err) {
                console.error('复制失败:', err);
                showAlert('复制失败，请手动复制链接', 'danger');
                closeModal('shareOptionsModal');
            }
        }

        // 显示分享图
        function showShareImage() {
            if (!currentShareData) return;
            
            closeModal('shareOptionsModal');
            
            const avatarUrl = currentShareData.avatarUrl || 'http://q1.qlogo.cn/g?b=qq&nk=113377210&s=100';
            document.getElementById('posterAvatar').src = avatarUrl;
            document.getElementById('qrAvatar').src = avatarUrl;
            
            const posterEntryText = currentShareData.posterEntryText || '企业客服入口';
            document.getElementById('posterTag').textContent = posterEntryText;
            
            document.getElementById('posterName').textContent = currentShareData.pageTitle;
            
            const serviceHours = currentShareData.serviceHours || '9:00-18:00';
            const posterHours = document.getElementById('posterHours');
            if (serviceHours !== '未设置') {
                posterHours.textContent = serviceHours;
                posterHours.style.display = 'inline-flex';
            } else {
                posterHours.style.display = 'none';
            }
            
            const qrContainer = document.getElementById('shareQrCode');
            qrContainer.innerHTML = '';
            
            try {
                new QRCode(qrContainer, {
                    text: currentShareData.pageUrl,
                    width: 118,
                    height: 118,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            } catch (e) {
                console.error("二维码生成失败:", e);
                qrContainer.innerHTML = `<img src="https://chart.googleapis.com/chart?cht=qr&chs=118x118&chl=${encodeURIComponent(currentShareData.pageUrl)}" alt="二维码">`;
            }
            
            openModal('shareImageModal');
        }

        // 下载分享图
        function downloadShareImage() {
            const shareContainer = document.getElementById('shareImageContainer');
            
            html2canvas(shareContainer, {
                backgroundColor: '#ffffff',
                scale: 2,
                useCORS: true,
                logging: false,
                allowTaint: true
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = `客服页面分享图-${currentShareData.pageCode}.png`;
                link.href = canvas.toDataURL('image/png');
                link.click();
                showAlert('分享图已保存成功', 'success');
            }).catch(err => {
                console.error('生成图片失败:', err);
                showAlert('保存失败，请稍后重试', 'danger');
            });
        }

        // 处理表单提交
        async function handleFormSubmit(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const formData = new FormData(e.target);
            const isEdit = !!formData.get('page_id');
            
            submitText.textContent = isEdit ? '更新中...' : '添加中...';
            submitBtn.disabled = true;

            try {
                const action = isEdit ? 'edit_service_page' : 'add_service_page';
                formData.append('action', action);
                
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert(isEdit ? '客服页面更新成功！' : '客服页面创建成功！', 'success');
                    closeModal('servicePageModal');
                    e.target.reset();
                    loadServicePages();
                } else {
                    showAlert(result.message, 'danger');
                }
            } catch (error) {
                showAlert('网络请求失败: ' + error.message, 'danger');
            } finally {
                submitText.textContent = isEdit ? '更新客服页面' : '添加客服页面';
                submitBtn.disabled = false;
            }
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showAlert('页面代码已复制到剪贴板', 'success');
            });
        }

        function showAlert(message, type) {
            const notification = document.getElementById('notification');
            
            let icon = 'ℹ';
            let toastType = 'info';
            switch(type) {
                case 'success':
                    icon = '✓';
                    toastType = 'success';
                    break;
                case 'warning':
                    icon = '⚠';
                    toastType = 'warning';
                    break;
                case 'danger':
                case 'error':
                    icon = '✕';
                    toastType = 'error';
                    break;
            }
            
            notification.innerHTML = `
                <div class="toast-icon">${icon}</div>
                <div class="toast-text">${message}</div>
                <button class="toast-close" onclick="closeNotification()">×</button>
            `;
            
            notification.className = `new-user-toast ${toastType}`;
            
            notification.classList.remove('toast-hidden');
            notification.classList.add('notification-enter-active');
            
            setTimeout(() => {
                closeNotification();
            }, 5000);
        }
        
        function closeNotification() {
            const notification = document.getElementById('notification');
            notification.classList.remove('notification-enter-active');
            notification.classList.add('notification-leave-active');
            
            setTimeout(() => {
                notification.classList.add('toast-hidden');
                notification.classList.remove('notification-leave-active');
            }, 300);
        }

    </script>
    
    <!-- 通知提示 -->
    <div id="notification" class="new-user-toast toast-hidden"></div>
</body>
</html>