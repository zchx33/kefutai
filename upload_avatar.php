<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$response = array('success' => false, 'message' => '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = '请求方法错误';
    echo json_encode($response);
    exit;
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = '请选择要上传的图片';
    echo json_encode($response);
    exit;
}

$file = $_FILES['avatar'];

// 大小限制
if ($file['size'] > 2 * 1024 * 1024) {
    $response['message'] = '图片大小不能超过2MB';
    echo json_encode($response);
    exit;
}

// 安全检测：用 finfo 检测真实文件类型（不信任浏览器提供的 type）
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
$realMime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
} elseif (function_exists('mime_content_type')) {
    $realMime = mime_content_type($file['tmp_name']);
} else {
    $realMime = $file['type'];
}

if (!in_array($realMime, $allowedMimes)) {
    $response['message'] = '只支持 JPG, PNG, GIF 格式的图片';
    echo json_encode($response);
    exit;
}

// 安全检测：检查文件内容是否包含 PHP 代码
$fileContent = file_get_contents($file['tmp_name']);
if (preg_match('/<\?php|<\?=/i', $fileContent)) {
    $response['message'] = '文件包含非法内容';
    echo json_encode($response);
    exit;
}

// 安全检测：限制 upload_path 只能是预定义的目录
$uploadPath = isset($_POST['upload_path']) ? $_POST['upload_path'] : 'groupsettings';
$allowedPaths = ['groupsettings', 'avatars', 'groupchatimages'];
if (!in_array($uploadPath, $allowedPaths)) {
    $uploadPath = 'groupsettings';
}
// 防止目录穿越
$uploadPath = str_replace(['..', '/', '\\'], '', basename($uploadPath));

// 强制使用安全扩展名
$mimeToExt = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif'
];
$safeExtension = $mimeToExt[$realMime] ?? 'jpg';

$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $uploadPath . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileName = 'groupavatar_' . time() . '_' . uniqid() . '.' . $safeExtension;
$filePath = $uploadDir . $fileName;

if (move_uploaded_file($file['tmp_name'], $filePath)) {
    $relativePath = '/uploads/' . $uploadPath . '/' . $fileName;
    $response['success'] = true;
    $response['filename'] = $fileName;
    $response['filepath'] = $relativePath;
    $response['message'] = '上传成功';
} else {
    $response['message'] = '文件保存失败';
}

echo json_encode($response);
?>
