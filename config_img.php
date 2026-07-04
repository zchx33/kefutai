<?php

// api/images - 获取图片列表
header('Content-Type: application/json');

// 设置上传目录
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';

// 获取图片列表
function getImageList($dir) {
    $images = [];
    
    if (is_dir($dir)) {
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $filePath = $dir . $file;
                $fileInfo = pathinfo($filePath);
                
                // 检查是否为图片文件
                if (isset($fileInfo['extension']) && 
                    in_array(strtolower($fileInfo['extension']), ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                    
                    $images[] = [
                        'name' => $file,
                        'path' => $filePath,
                        'url' => '/uploads/' . $file,
                        'size' => filesize($filePath),
                        'modified' => filemtime($filePath)
                    ];
                }
            }
        }
    }
    
    return $images;
}

// 获取图片列表
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $images = getImageList($uploadDir);
    echo json_encode($images);
    exit;
}

// api/images/delete - 删除单张图片
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['path'])) {
    $response = ['success' => false, 'message' => ''];
    
    $filePath = $_POST['path'];
    
    // 安全检查：确保文件在uploads目录内
    if (strpos(realpath($filePath), realpath($uploadDir)) === 0 && file_exists($filePath)) {
        if (unlink($filePath)) {
            $response['success'] = true;
            $response['message'] = '文件删除成功';
        } else {
            $response['message'] = '文件删除失败';
        }
    } else {
        $response['message'] = '文件不存在或路径无效';
    }
    
    echo json_encode($response);
    exit;
}

// api/images/clean - 清理未使用图片
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => '', 'deletedCount' => 0];
    
    // 获取所有图片
    $allImages = getImageList($uploadDir);
    
    // 这里需要根据您的系统实现来检测哪些图片未被使用
    // 以下是一个示例实现，您需要根据实际情况调整
    
    $usedImages = getUsedImages(); // 获取被使用的图片列表
    $deletedCount = 0;
    
    foreach ($allImages as $image) {
        if (!in_array($image['name'], $usedImages)) {
            if (unlink($image['path'])) {
                $deletedCount++;
            }
        }
    }
    
    $response['success'] = true;
    $response['deletedCount'] = $deletedCount;
    $response['message'] = "清理完成，删除了 {$deletedCount} 个未使用图片";
    
    echo json_encode($response);
    exit;
}

// 示例函数：获取被使用的图片列表（需要根据您的系统调整）
function getUsedImages() {
    $usedImages = [];
    
    // 这里需要扫描您的项目文件，查找被引用的图片
    // 例如扫描HTML、CSS、JS文件等，提取图片引用
    
    // 示例：扫描项目目录中的文件
    $projectRoot = $_SERVER['DOCUMENT_ROOT'];
    scanDirectoryForImages($projectRoot, $usedImages);
    
    return array_unique($usedImages);
}

// 递归扫描目录查找图片引用
function scanDirectoryForImages($dir, &$images) {
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($filePath)) {
            // 跳过uploads目录和常见依赖目录
            if (strpos($filePath, 'uploads') === false && 
                strpos($filePath, 'node_modules') === false &&
                strpos($filePath, 'vendor') === false) {
                scanDirectoryForImages($filePath, $images);
            }
        } else {
            // 检查文件类型
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if (in_array($ext, ['html', 'htm', 'php', 'css', 'js'])) {
                $content = file_get_contents($filePath);
                
                // 查找图片引用（简单正则匹配）
                preg_match_all('/\/uploads\/([^\s"\'\)>]+\.(jpg|jpeg|png|gif|bmp|webp))/i', $content, $matches);
                
                if (!empty($matches[1])) {
                    $images = array_merge($images, $matches[1]);
                }
            }
        }
    }
}
?>