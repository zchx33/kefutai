<?php

class SessionParser {
    public static function parseSessionId($sessionId) {
        if (empty($sessionId)) {
            return ['customer' => 'default', 'agent' => 'testadmin'];
        }
        
        // 检查是否是下划线格式 (customer_agent)
        if (strpos($sessionId, '_') !== false) {
            $parts = explode('_', $sessionId);
            if (count($parts) === 2) {
                return [
                    'customer' => $parts[0] ?: 'default',
                    'agent' => $parts[1] ?: 'testadmin'
                ];
            }
        }
        
        // 分割客户和客服部分 (旧格式)
        $parts = explode('-', $sessionId);
        if (count($parts) !== 2) {
            return ['customer' => 'default', 'agent' => 'testadmin'];
        }
        
        $customerPart = $parts[0]; // aaadcazzz
        $agentPart = $parts[1];    // ptestadmins
        
        // 解析客户名称: 去掉首尾的a和z
        $customerName = substr($customerPart, 1, -1); // aadcazz
        
        // 解析客服账号: 去掉首尾的p和s
        $agentAccount = substr($agentPart, 1, -1); // testadmin
        
        return [
            'customer' => $customerName ?: 'default',
            'agent' => $agentAccount ?: 'testadmin'
        ];
    }
    
    /**
     * 生成会话key (使用下划线格式)
     */
    public static function generateSessionKey($customer, $agent) {
        return $customer . '_' . $agent;
    }
    
    /**
     * 生成会话ID (旧格式，用于兼容)
     */
    public static function generateSessionId($customer, $agent) {
        return 'a' . $customer . 'z-p' . $agent . 's';
    }
    
    /**
     * 统一获取会话key
     */
    public static function getSessionKey($sessionId) {
        $parsed = self::parseSessionId($sessionId);
        return self::generateSessionKey($parsed['customer'], $parsed['agent']);
    }
}
?>