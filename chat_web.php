<?php
session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';

// 记录访问量的函数
function recordVisit() {
    $db = getDB();
    if (!$db) {
        error_log("无法获取数据库连接");
        return;
    }
    
    $today = date('Y-m-d');
    $ip = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $pageUrl = $_SERVER['REQUEST_URI'];
    $sessionId = session_id();
    
    // 记录访问日志
    $stmt = $db->prepare("INSERT INTO visit_logs (ip_address, user_agent, referrer, page_url, session_id) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sssss", $ip, $userAgent, $referrer, $pageUrl, $sessionId);
        if (!$stmt->execute()) {
            error_log("记录访问日志失败: " . $db->error);
        }
    } else {
        error_log("准备访问日志语句失败: " . $db->error);
    }
    
    // 检查是否是独立访客（基于IP和会话ID）
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visit_logs WHERE ip_address = ? AND session_id = ? AND DATE(created_at) = ?");
    if ($stmt) {
        $stmt->bind_param("sss", $ip, $sessionId, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $row = $result->fetch_assoc();
            $isUnique = ($row['count'] <= 1);
            
            // 更新或插入今日统计
            $stmt = $db->prepare("SELECT id, visits, unique_visitors, page_views FROM site_visits WHERE visit_date = ?");
            if ($stmt) {
                $stmt->bind_param("s", $today);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    // 更新现有记录
                    $row = $result->fetch_assoc();
                    $visits = $row['visits'] + 1;
                    $page_views = $row['page_views'] + 1;
                    
                    if ($isUnique) {
                        $unique_visitors = $row['unique_visitors'] + 1;
                        $stmt = $db->prepare("UPDATE site_visits SET visits = ?, unique_visitors = ?, page_views = ? WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("iiii", $visits, $unique_visitors, $page_views, $row['id']);
                        }
                    } else {
                        $stmt = $db->prepare("UPDATE site_visits SET visits = ?, page_views = ? WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("iii", $visits, $page_views, $row['id']);
                        }
                    }
                } else {
                    // 插入新记录
                    if ($isUnique) {
                        $stmt = $db->prepare("INSERT INTO site_visits (visit_date, visits, unique_visitors, page_views) VALUES (?, 1, 1, 1)");
                    } else {
                        $stmt = $db->prepare("INSERT INTO site_visits (visit_date, visits, unique_visitors, page_views) VALUES (?, 1, 0, 1)");
                    }
                    if ($stmt) {
                        $stmt->bind_param("s", $today);
                    }
                }
                
                if ($stmt) {
                    if (!$stmt->execute()) {
                        error_log("更新访问统计失败: " . $db->error);
                    }
                }
            }
        }
    }
}

// 调试函数：手动增加访问量
function addTestVisit() {
    $db = getDB();
    if (!$db) return;
    
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    // 为今天添加测试数据
    $stmt = $db->prepare("SELECT id FROM site_visits WHERE visit_date = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt = $db->prepare("UPDATE site_visits SET visits = visits + 5, unique_visitors = unique_visitors + 3, page_views = page_views + 8 WHERE id = ?");
        $stmt->bind_param("i", $row['id']);
    } else {
        $stmt = $db->prepare("INSERT INTO site_visits (visit_date, visits, unique_visitors, page_views) VALUES (?, 5, 3, 8)");
        $stmt->bind_param("s", $today);
    }
    $stmt->execute();
    
    // 为昨天添加测试数据
    $stmt = $db->prepare("SELECT id FROM site_visits WHERE visit_date = ?");
    $stmt->bind_param("s", $yesterday);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt = $db->prepare("UPDATE site_visits SET visits = visits + 8, unique_visitors = unique_visitors + 4, page_views = page_views + 12 WHERE id = ?");
        $stmt->bind_param("i", $row['id']);
    } else {
        $stmt = $db->prepare("INSERT INTO site_visits (visit_date, visits, unique_visitors, page_views) VALUES (?, 8, 4, 12)");
        $stmt->bind_param("s", $yesterday);
    }
    $stmt->execute();
    
    return "测试数据已添加";
}