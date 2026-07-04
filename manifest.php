<?php
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';

$db = getDB();

function getWebConfig($db) {
    $result = $db->query("SELECT * FROM webconfig ORDER BY id DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return [
        'pwa_name' => 'XE控制台',
        'pwa_short_name' => 'XE控制台',
        'pwa_icon' => '/xe-icon.png'
    ];
}

$config = getWebConfig($db);

$manifest = [
    "name" => $config['pwa_name'] ?? 'XE控制台',
    "short_name" => $config['pwa_short_name'] ?? 'XE控制台',
    "start_url" => "/",
    "scope" => "/",
    "display" => "standalone",
    "background_color" => "#ffffff",
    "theme_color" => "#ffffff",
    "gcm_sender_id" => "103953800507",
    "icons" => [
        [
            "src" => $config['pwa_icon'] ?? '/xe-icon.png',
            "sizes" => "192x192",
            "type" => "image/png",
            "purpose" => "any maskable"
        ],
        [
            "src" => $config['pwa_icon'] ?? '/xe-icon.png',
            "sizes" => "512x512",
            "type" => "image/png",
            "purpose" => "any maskable"
        ]
    ]
];

echo json_encode($manifest, JSON_UNESCAPED_UNICODE);
?>