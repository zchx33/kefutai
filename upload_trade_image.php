<?php
header('Content-Type: application/json');

$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/groupchatimages/';

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '只支持POST请求']);
    exit;
}

if (!isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'error' => '没有上传文件']);
    exit;
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => '文件上传失败，错误码：' . $file['error']]);
    exit;
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (function_exists('mime_content_type')) {
    $fileType = mime_content_type($file['tmp_name']);
} elseif (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $fileType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
} else {
    $fileType = $file['type'];
}

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => '不支持的文件类型：' . $fileType]);
    exit;
}

// 安全检测：检查文件内容是否包含 PHP 代码
$fileContent = file_get_contents($file['tmp_name']);
if (preg_match('/<\?php|<\?=/i', $fileContent)) {
    echo json_encode(['success' => false, 'error' => '文件包含非法内容']);
    exit;
}

$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => '文件大小超过5MB限制']);
    exit;
}

$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
if (empty($extension)) {
    $extensionMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    $extension = isset($extensionMap[$fileType]) ? $extensionMap[$fileType] : 'jpg';
}
$filename = uniqid('trade_') . '_' . time() . '.' . $extension;
$targetPath = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $imageUrl = '/uploads/groupchatimages/' . $filename;
    echo json_encode([
        'success' => true,
        'url' => $imageUrl,
        'filename' => $filename
    ]);
} else {
    echo json_encode(['success' => false, 'error' => '文件移动失败，请检查目录权限']);
}
