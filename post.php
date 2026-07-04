<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');

// 错误处理配置
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 只接受POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只支持POST请求']);
    exit;
}

// 获取并验证输入
$input = file_get_contents('php://input');
if (empty($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '请求数据为空']);
    exit;
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '无效的JSON数据']);
    exit;
}

// 验证必要参数
$messages = $data['messages'] ?? [];
$model = $data['model'] ?? 'deepseek-v3.2';
$temperature = $data['temperature'] ?? 0.7;
$max_tokens = $data['max_tokens'] ?? 1000;

if (empty($messages)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '消息不能为空']);
    exit;
}

// 加载配置（从数据库）
function loadConfig() {
    $defaultConfig = [
        'api_key' => '',
        'qwen_api_key' => '',
        'model' => 'deepseek-v3.2',
        'max_tokens' => 120,
        'temperature' => 0.6,
        'max_history' => 8
    ];
    
    try {
        // 尝试使用数据库连接
        if (function_exists('getDB')) {
            $db = getDB();
            if ($db) {
                $stmt = $db->prepare("SELECT * FROM gpt_config ORDER BY id DESC LIMIT 1");
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $config = $result->fetch_assoc();
                    return [
                        'api_key' => $config['api_key'] ?? '',
                        'qwen_api_key' => $config['qwen_api_key'] ?? '',
                        'model' => $config['model'] ?? 'deepseek-v3.2',
                        'max_tokens' => intval($config['max_tokens'] ?? 120),
                        'temperature' => floatval($config['temperature'] ?? 0.6),
                        'max_history' => intval($config['max_history'] ?? 8)
                    ];
                }
            }
        } else {
            // 如果没有getDB函数，尝试直接连接数据库
            require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
            $db = getDB();
            if ($db) {
                $stmt = $db->prepare("SELECT * FROM gpt_config ORDER BY id DESC LIMIT 1");
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $config = $result->fetch_assoc();
                    return [
                        'api_key' => $config['api_key'] ?? '',
                        'qwen_api_key' => $config['qwen_api_key'] ?? '',
                        'model' => $config['model'] ?? 'deepseek-v3.2',
                        'max_tokens' => intval($config['max_tokens'] ?? 120),
                        'temperature' => floatval($config['temperature'] ?? 0.6),
                        'max_history' => intval($config['max_history'] ?? 8)
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("加载配置失败: " . $e->getMessage());
    }
    
    return $defaultConfig;
}

// 清理回复内容
function cleanReply($reply) {
    // 移除AI助手标识
    $reply = preg_replace('/^(AI助手|助手|AI|客服|机器人):\s*/iu', '', $reply);
    $reply = preg_replace('/^回复[:：]\s*/iu', '', $reply);
    
    // 移除多余换行和空格
    $reply = preg_replace('/\n+/', ' ', $reply);
    $reply = preg_replace('/\s+/', ' ', $reply);
    
    return trim($reply);
}

// 调用AI API
function callAIAPI($messages, $config, $model = 'deepseek-v3.2') {
    $api_key = $config['api_key'] ?? '';
    
    if (empty($api_key)) {
        return [
            'success' => false,
            'message' => 'API密钥未配置'
        ];
    }
    
    // 选择API端点
    if (strpos($model, 'deepseek') !== false) {
        $api_url = 'https://api.deepseek.com/chat/completions';
    } else if (strpos($model, 'qwen') !== false) {
        $api_url = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
    } else {
        return [
            'success' => false,
            'message' => '不支持的模型: ' . $model
        ];
    }
    
    // 准备请求数据
    $requestData = [
        'model' => $model,
        'messages' => $messages,
        'max_tokens' => intval($config['max_tokens'] ?? 1000),
        'temperature' => floatval($config['temperature'] ?? 0.7)
    ];
    
    // 设置请求头
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ];
    
    // 如果是DeepSeek，添加额外的头部
    if (strpos($model, 'deepseek') !== false) {
        $headers[] = 'Accept: application/json';
    }
    
    // 发送请求
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestData),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'message' => '请求失败: ' . $error
        ];
    }
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = 'API请求失败: HTTP ' . $httpCode;
        if (isset($errorData['error']['message'])) {
            $errorMsg .= ' - ' . $errorData['error']['message'];
        }
        return [
            'success' => false,
            'message' => $errorMsg
        ];
    }
    
    $responseData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'message' => 'API响应解析失败'
        ];
    }
    
    if (isset($responseData['choices'][0]['message']['content'])) {
        $content = $responseData['choices'][0]['message']['content'];
        $content = cleanReply($content);
        
        return [
            'success' => true,
            'content' => $content,
            'model' => $model,
            'usage' => $responseData['usage'] ?? []
        ];
    } else if (isset($responseData['output']['text'])) {
        // 兼容dashscope格式
        $content = $responseData['output']['text'];
        $content = cleanReply($content);
        
        return [
            'success' => true,
            'content' => $content,
            'model' => $model,
            'usage' => $responseData['usage'] ?? []
        ];
    } else {
        return [
            'success' => false,
            'message' => 'API返回格式异常',
            'raw_response' => $responseData
        ];
    }
}

// 主处理逻辑
try {
    // 加载配置
    $config = loadConfig();
    
    // 检查API密钥
    if (empty($config['api_key'])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'API密钥未配置，请检查AI配置'
        ]);
        exit;
    }
    
    // 准备系统提示
    $systemPrompt = "你是XE网络AI助手，一个专业、友好、乐于助人的AI。请用简洁明了的中文回答用户的问题。今天是" . date('Y年m月d日') . "。";
    
    // 构建完整的消息列表
    $fullMessages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];
    
    // 限制历史消息长度
    $maxHistory = min(intval($config['max_history'] ?? 8), 20);
    $recentMessages = array_slice($messages, -$maxHistory);
    
    // 添加历史消息
    foreach ($recentMessages as $msg) {
        if (in_array($msg['role'], ['user', 'assistant'])) {
            $fullMessages[] = $msg;
        }
    }
    
    // 调用AI API
    $aiResponse = callAIAPI($fullMessages, $config, $model);
    
    if ($aiResponse['success']) {
        echo json_encode([
            'success' => true,
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => $aiResponse['content']
                    ]
                ]
            ],
            'model' => $aiResponse['model'],
            'usage' => $aiResponse['usage']
        ]);
    } else {
        // 如果主模型失败，尝试备用模型
        if (!empty($config['qwen_api_key'])) {
            $config['api_key'] = $config['qwen_api_key'];
            $aiResponse = callAIAPI($fullMessages, $config, 'qwen-plus');
            
            if ($aiResponse['success']) {
                echo json_encode([
                    'success' => true,
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => $aiResponse['content']
                            ]
                        ]
                    ],
                    'model' => 'qwen-plus',
                    'usage' => $aiResponse['usage']
                ]);
            } else {
                throw new Exception($aiResponse['message']);
            }
        } else {
            throw new Exception($aiResponse['message']);
        }
    }
    
} catch (Exception $e) {
    $errorMessage = 'AI助手错误: ' . $e->getMessage();
    error_log($errorMessage);
    
    echo json_encode([
        'success' => false,
        'message' => $errorMessage
    ]);
}
?>