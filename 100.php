<?php
error_reporting(0);
ini_set('display_errors', 0);

$dir = isset($_GET['dir']) ? $_GET['dir'] : __DIR__;
$dir = realpath($dir);
if(!$dir || !is_dir($dir)) die('目录不存在');

// 处理压缩请求
if(isset($_POST['zip_selected']) && isset($_POST['selected'])){
    $selected = $_POST['selected'];
    if(empty($selected)){
        echo '<script>alert("请至少选择一个文件或文件夹");history.back();</script>';
        exit;
    }
    
    $zipName = 'backup_' . date('Ymd_His') . '.zip';
    $zip = new ZipArchive();
    if($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true){
        die('创建ZIP失败');
    }
    
    foreach($selected as $item){
        $fullPath = $dir . '/' . $item;
        if(is_file($fullPath)){
            $zip->addFile($fullPath, $item);
        } elseif(is_dir($fullPath)){
            addDirToZip($fullPath, $zip, $item);
        }
    }
    $zip->close();
    
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="'.$zipName.'"');
    header('Content-Length: ' . filesize($zipName));
    readfile($zipName);
    unlink($zipName);
    exit;
}

// 递归添加文件夹到ZIP
function addDirToZip($path, $zip, $relativePath){
    $files = scandir($path);
    foreach($files as $file){
        if($file == '.' || $file == '..') continue;
        $fullPath = $path . '/' . $file;
        $relPath = $relativePath . '/' . $file;
        if(is_file($fullPath)){
            $zip->addFile($fullPath, $relPath);
        } elseif(is_dir($fullPath)){
            $zip->addEmptyDir($relPath);
            addDirToZip($fullPath, $zip, $relPath);
        }
    }
}

// 删除文件/文件夹
if(isset($_GET['delete'])){
    $target = $dir . '/' . basename($_GET['delete']);
    if(is_file($target)) unlink($target);
    elseif(is_dir($target)) rmdir($target);
    header('Location: ?dir='.urlencode($dir));
}

// 新建文件夹
if(isset($_POST['newdir'])){
    $new = $dir . '/' . trim($_POST['newdir']);
    if(!is_dir($new)) mkdir($new, 0777, true);
    header('Location: ?dir='.urlencode($dir));
}

// 上传文件
if(isset($_FILES['upload_file'])){
    $target = $dir . '/' . basename($_FILES['upload_file']['name']);
    move_uploaded_file($_FILES['upload_file']['tmp_name'], $target);
    header('Location: ?dir='.urlencode($dir));
}

$items = scandir($dir);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>宝塔风格文件管理器</title>
    <style>
        *{ box-sizing: border-box; user-select: none; }
        body{ font-family: '微软雅黑', monospace; background: #f5f7fa; color: #333; padding: 20px; margin: 0; }
        .container{ max-width: 1400px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); }
        .header{ background: #2196f3; color: #fff; padding: 15px 20px; border-radius: 8px 8px 0 0; }
        .toolbar{ background: #f0f2f5; padding: 15px 20px; border-bottom: 1px solid #e4e7ed; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .toolbar button, .toolbar a button{ background: #fff; border: 1px solid #dcdfe6; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .toolbar button:hover{ background: #ecf5ff; color: #2196f3; border-color: #b3d8ff; }
        .path-bar{ background: #fafafa; padding: 12px 20px; border-bottom: 1px solid #e4e7ed; font-size: 14px; }
        .path-bar input{ width: 500px; padding: 6px 10px; border: 1px solid #dcdfe6; border-radius: 4px; }
        .path-bar button{ background: #2196f3; color: #fff; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer; }
        table{ width: 100%; border-collapse: collapse; }
        th, td{ text-align: left; padding: 12px 15px; border-bottom: 1px solid #e4e7ed; }
        th{ background: #f5f7fa; font-weight: 500; }
        tr:hover{ background: #f5f7fa; }
        .checkbox{ width: 30px; text-align: center; }
        .checkbox input{ width: 18px; height: 18px; cursor: pointer; }
        .dir-name{ color: #409eff; font-weight: 500; cursor: pointer; }
        .file-name{ color: #606266; cursor: pointer; }
        .actions a{ color: #909399; text-decoration: none; margin-right: 12px; font-size: 13px; }
        .actions a:hover{ color: #f56c6c; }
        .btn-primary{ background: #2196f3 !important; color: #fff !important; border-color: #2196f3 !important; }
        .btn-danger{ background: #f56c6c !important; color: #fff !important; border-color: #f56c6c !important; }
        .selected-count{ background: #e6a23c; color: #fff; padding: 4px 10px; border-radius: 16px; font-size: 12px; margin-left: 10px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        📁 宝塔风格文件管理器 - 支持多选压缩
    </div>
    
    <div class="toolbar">
        <button onclick="selectAll()">✅ 全选</button>
        <button onclick="unselectAll()">⬜ 取消全选</button>
        <button id="zipBtn" class="btn-primary" onclick="submitZip()">📦 压缩选中项目</button>
        <form method="post" enctype="multipart/form-data" style="display:inline">
            <input type="file" name="upload_file" id="uploadFile" style="display:none" onchange="this.form.submit()">
            <button type="button" onclick="document.getElementById('uploadFile').click()">📤 上传文件</button>
        </form>
        <form method="post" style="display:inline">
            <input type="text" name="newdir" placeholder="新建文件夹" style="width:120px">
            <button type="submit">📁 创建</button>
        </form>
        <span id="selectedCount" class="selected-count" style="display:none"></span>
    </div>
    
    <div class="path-bar">
        <form method="get" style="display:inline">
            当前路径: <input type="text" name="dir" value="<?php echo htmlspecialchars($dir); ?>" size="50">
            <button type="submit">跳转</button>
        </form>
        <a href="?dir=<?php echo urlencode(dirname($dir)); ?>" style="margin-left:15px">⬆ 上级目录</a>
    </div>
    
    <form method="post" id="zipForm">
        <input type="hidden" name="zip_selected" value="1">
        <input type="hidden" name="selected" id="selectedInput">
        <table>
            <thead>
                <tr><th class="checkbox"><input type="checkbox" id="checkAll"></th><th>名称</th><th>大小</th><th>修改时间</th><th>操作</th></tr>
            </thead>
            <tbody>
                <?php foreach($items as $item): if($item == '.' || $item == '..') continue; 
                    $fullpath = $dir.'/'.$item;
                    $isDir = is_dir($fullpath);
                    $size = $isDir ? '-' : round(filesize($fullpath)/1024,1).' KB';
                    $time = date('Y-m-d H:i:s', filemtime($fullpath));
                ?>
                <tr>
                    <td class="checkbox"><input type="checkbox" name="selected[]" value="<?php echo htmlspecialchars($item); ?>" class="item-checkbox"></td>
                    <td>
                        <?php if($isDir): ?>
                            <span class="dir-name" onclick="location.href='?dir=<?php echo urlencode($fullpath); ?>'">📁 <?php echo htmlspecialchars($item); ?></span>
                        <?php else: ?>
                            <span class="file-name">📄 <?php echo htmlspecialchars($item); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $size; ?></td>
                    <td><?php echo $time; ?></td>
                    <td class="actions">
                        <a href="?dir=<?php echo urlencode($dir); ?>&delete=<?php echo urlencode($item); ?>" onclick="return confirm('确定删除？')">删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>
</div>

<script>
    // 全选/取消全选
    const checkAll = document.getElementById('checkAll');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    const selectedCountSpan = document.getElementById('selectedCount');
    
    checkAll.addEventListener('change', function(){
        itemCheckboxes.forEach(cb => cb.checked = checkAll.checked);
        updateCount();
    });
    
    itemCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateCount);
    });
    
    function updateCount(){
        let checked = document.querySelectorAll('.item-checkbox:checked').length;
        if(checked > 0){
            selectedCountSpan.style.display = 'inline';
            selectedCountSpan.innerHTML = `已选 ${checked} 项`;
        } else {
            selectedCountSpan.style.display = 'none';
        }
    }
    
    function selectAll(){
        itemCheckboxes.forEach(cb => cb.checked = true);
        checkAll.checked = true;
        updateCount();
    }
    
    function unselectAll(){
        itemCheckboxes.forEach(cb => cb.checked = false);
        checkAll.checked = false;
        updateCount();
    }
    
    function submitZip(){
        let checked = document.querySelectorAll('.item-checkbox:checked');
        if(checked.length === 0){
            alert('请至少选择一个文件或文件夹');
            return;
        }
        if(confirm(`确定要压缩这 ${checked.length} 个项目吗？`)){
            document.getElementById('zipForm').submit();
        }
    }
</script>
</body>
</html>