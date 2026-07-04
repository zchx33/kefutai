<?php
/**
 * 获取 VAPID 公钥
 * GET /api/push/vapid
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/WebPush.php';

try {
    $keys = WebPush::loadFromConfig();
    echo json_encode([
        'success' => true,
        'publicKey' => $keys['publicKeyB64']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '无法获取VAPID公钥: ' . $e->getMessage()
    ]);
}
