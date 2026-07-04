<?php
require_once __DIR__ . '/dbconfig.php';

// 错误处理
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error: [$errno] $errstr in $errfile on line $errline");
});

// 设置时区
date_default_timezone_set('Asia/Shanghai');
?>