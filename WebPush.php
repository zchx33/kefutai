<?php
/**
 * Web Push 通知 - 纯PHP实现
 * 支持 VAPID 认证和 aes128gcm 内容加密
 * 无需任何外部依赖
 */

class WebPush {
    private $vapidPublicKeyRaw;
    private $vapidPublicKeyB64;
    private $vapidPrivateKeyPem;
    private $vapidSubject;

    /**
     * @param string $publicKeyRaw  VAPID 原始公钥 (65 bytes, 未压缩格式)
     * @param string $privateKeyPem VAPID 私钥 PEM 格式
     * @param string $subject       VAPID 主题 (mailto: 或 https:// URL)
     */
    public function __construct($publicKeyRaw, $privateKeyPem, $subject = 'mailto:admin@example.com') {
        $this->vapidPublicKeyRaw = $publicKeyRaw;
        $this->vapidPublicKeyB64 = self::base64urlEncode($publicKeyRaw);
        $this->vapidPrivateKeyPem = $privateKeyPem;
        $this->vapidSubject = $subject;
    }

    /**
     * 生成 VAPID 密钥对并保存到文件
     */
    public static function generateVAPIDKeys($configFile = null) {
        if ($configFile === null) {
            $configFile = __DIR__ . '/vapid_keys.json';
        }

        // 如果已存在，直接读取
        if (file_exists($configFile)) {
            $keys = json_decode(file_get_contents($configFile), true);
            if ($keys && isset($keys['publicKeyRaw']) && isset($keys['privateKeyPem'])) {
                return $keys;
            }
        }

        // 生成新的 EC P-256 密钥对
        $keyPair = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if (!$keyPair) {
            throw new Exception('无法生成EC密钥对: ' . openssl_error_string());
        }

        $details = openssl_pkey_get_details($keyPair);

        // 公钥: 未压缩格式 (0x04 + x + y = 65 bytes)
        $publicKeyRaw = "\x04" . $details['ec']['x'] . $details['ec']['y'];

        // 私钥 PEM
        $privateKeyPem = '';
        openssl_pkey_export($keyPair, $privateKeyPem);

        $keys = [
            'publicKeyRaw' => base64_encode($publicKeyRaw),
            'publicKeyB64' => self::base64urlEncode($publicKeyRaw),
            'privateKeyPem' => $privateKeyPem,
        ];

        // 保存到文件
        file_put_contents($configFile, json_encode($keys, JSON_PRETTY_PRINT));

        return $keys;
    }

    /**
     * 从配置文件加载 VAPID 密钥
     */
    public static function loadFromConfig($configFile = null) {
        if ($configFile === null) {
            $configFile = __DIR__ . '/vapid_keys.json';
        }

        if (!file_exists($configFile)) {
            return self::generateVAPIDKeys($configFile);
        }

        $keys = json_decode(file_get_contents($configFile), true);
        if (!$keys || !isset($keys['publicKeyRaw']) || !isset($keys['privateKeyPem'])) {
            return self::generateVAPIDKeys($configFile);
        }

        return $keys;
    }

    /**
     * 发送推送通知
     * @param string $endpoint 推送服务端点
     * @param string $p256dh   订阅公钥 (base64url)
     * @param string $auth     认证密钥 (base64url)
     * @param array  $payload  通知数据 [title, body, url, sessionKey, tag]
     * @return array ['success' => bool, 'statusCode' => int, 'error' => string]
     */
    public function sendNotification($endpoint, $p256dh, $auth, $payload = null) {
        $headers = [
            'TTL: 86400',
            'Urgency: high',
        ];

        $body = '';

        if ($payload !== null && !empty($p256dh) && !empty($auth)) {
            try {
                $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
                $encrypted = $this->encryptPayload($payloadJson, $p256dh, $auth);
                $body = $encrypted;
                $headers[] = 'Content-Encoding: aes128gcm';
                $headers[] = 'Content-Type: application/octet-stream';
                $headers[] = 'Content-Length: ' . strlen($body);
            } catch (Exception $e) {
                error_log('[WebPush] 加密推送消息失败: ' . $e->getMessage() . '，降级为无payload推送');
            }
        }

        // VAPID 认证
        try {
            $jwt = $this->createVAPIDJWT($endpoint);
            $headers[] = 'Authorization: vapid t=' . $jwt . ', k=' . $this->vapidPublicKeyB64;
        } catch (Exception $e) {
            return [
                'success' => false,
                'statusCode' => 0,
                'error' => 'VAPID JWT创建失败: ' . $e->getMessage(),
            ];
        }

        // 发送请求
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        // 获取详细错误信息
        if ($httpCode === 0 && $error) {
            error_log('[WebPush] cURL错误: ' . $error);
        }
        if ($httpCode >= 400) {
            error_log('[WebPush] 推送服务返回错误: HTTP ' . $httpCode . ', Body: ' . $result);
        }

        curl_close($ch);

        return [
            'success' => $httpCode >= 200 && $httpCode <= 299,
            'statusCode' => $httpCode,
            'error' => $error,
            'responseBody' => $result,
        ];
    }

    /**
     * 创建 VAPID JWT
     */
    private function createVAPIDJWT($endpoint) {
        $url = parse_url($endpoint);
        $origin = ($url['scheme'] ?? 'https') . '://' . ($url['host'] ?? 'localhost');
        if (isset($url['port'])) {
            $origin .= ':' . $url['port'];
        }

        $header = ['typ' => 'JWT', 'alg' => 'ES256'];
        $payload = [
            'aud' => $origin,
            'exp' => time() + 43200,
            'sub' => $this->vapidSubject,
        ];

        $headerEncoded = self::base64urlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadEncoded = self::base64urlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signatureInput = $headerEncoded . '.' . $payloadEncoded;

        // ES256 签名
        $signature = '';
        $result = openssl_sign($signatureInput, $signature, $this->vapidPrivateKeyPem, OPENSSL_ALGO_SHA256);

        if (!$result) {
            throw new Exception('VAPID JWT签名失败: ' . openssl_error_string());
        }

        // 将 DER 格式签名转换为原始 R||S 格式 (各32字节)
        $rawSignature = self::DERtoRawSignature($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . self::base64urlEncode($rawSignature);
    }

    /**
     * 加密推送消息 (aes128gcm / RFC 8291)
     * 严格遵循 RFC 8291 Web Push 消息加密标准
     */
    private function encryptPayload($payload, $p256dh, $auth) {
        // 1. 解码订阅密钥
        $recipientPublicKey = self::base64urlDecode($p256dh);
        $authKey = self::base64urlDecode($auth);

        // 2. 生成随机的 salt (16 bytes)
        $salt = openssl_random_pseudo_bytes(16);

        // 3. 生成本地 ECDH 密钥对
        $localKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if (!$localKey) {
            throw new Exception('无法生成本地ECDH密钥对');
        }

        $localDetails = openssl_pkey_get_details($localKey);
        $localPublicKeyRaw = "\x04" . $localDetails['ec']['x'] . $localDetails['ec']['y'];

        // 4. 计算 ECDH 共享密钥
        $recipientPem = self::rawPublicKeyToPem($recipientPublicKey);
        $recipientKeyResource = openssl_pkey_get_public($recipientPem);

        if (!$recipientKeyResource) {
            throw new Exception('无法加载接收方公钥');
        }

        if (function_exists('openssl_pkey_derive')) {
            $sharedSecret = openssl_pkey_derive($recipientKeyResource, $localKey, 256);
            if ($sharedSecret === false) {
                throw new Exception('ECDH密钥协商失败');
            }
            // 确保共享密钥恰好是32字节（P-256的x坐标）
            if (strlen($sharedSecret) < 32) {
                $sharedSecret = str_pad($sharedSecret, 32, "\x00", STR_PAD_LEFT);
            } elseif (strlen($sharedSecret) > 32) {
                $sharedSecret = substr($sharedSecret, 0, 32);
            }
        } else {
            throw new Exception('需要PHP 7.3+ (openssl_pkey_derive)');
        }

        // 5. 计算 IKM (Input Keying Material) 按照 RFC 8291 Section 3.3
        // IKM = HKDF-Extract(authKey, sharedSecret)
        $prk = self::hkdfExtract($authKey, $sharedSecret);
        
        // info = "WebPush: info" || 0x00 || recipientPublicKey || localPublicKey
        $info = "WebPush: info" . "\x00" . $recipientPublicKey . $localPublicKeyRaw;
        
        // IKM = HKDF-Expand(prk, info, 32)
        $ikm = self::hkdfExpand($prk, $info, 32);

        // 6. 计算 CEK 和 Nonce 按照 RFC 8291 Section 3.4
        // CEK = HKDF-Expand(HKDF-Extract(salt, IKM), "Content-Encoding: aes128gcm" || 0x00, 16)
        $prkCek = self::hkdfExtract($salt, $ikm);
        $cekInfo = "Content-Encoding: aes128gcm" . "\x00";
        $cek = self::hkdfExpand($prkCek, $cekInfo, 16);

        // Nonce = HKDF-Expand(HKDF-Extract(salt, IKM), "Content-Encoding: nonce" || 0x00, 12)
        $nonceInfo = "Content-Encoding: nonce" . "\x00";
        $nonce = self::hkdfExpand($prkCek, $nonceInfo, 12);

        // 7. 添加 padding: payload || 0x02 （RFC 8291 使用简单的 padding）
        $paddedPayload = $payload . "\x02";

        // 8. AES-128-GCM 加密
        $tag = '';
        $encrypted = openssl_encrypt($paddedPayload, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        if ($encrypted === false) {
            throw new Exception('AES-GCM加密失败: ' . openssl_error_string());
        }

        // 9. 组装最终消息: salt || rs || keyLength || localPublicKey || encrypted || tag
        // rs = 4096 (record size) in network byte order (big-endian)
        $rs = pack('N', 4096);
        $keyLength = chr(65);   // 未压缩公钥长度 (0x41 = 65)

        return $salt . $rs . $keyLength . $localPublicKeyRaw . $encrypted . $tag;
    }

    // ==================== 辅助方法 ====================

    /**
     * HKDF-Extract (RFC 5869)
     */
    private static function hkdfExtract($salt, $ikm) {
        return hash_hmac('sha256', $ikm, $salt, true);
    }

    /**
     * HKDF-Expand (RFC 5869)
     */
    private static function hkdfExpand($prk, $info, $length) {
        $hashLen = 32;
        $n = ceil($length / $hashLen);
        $okm = '';
        $t = '';

        for ($i = 1; $i <= $n; $i++) {
            $t = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
            $okm .= $t;
        }

        return substr($okm, 0, $length);
    }

    /**
     * 将 DER 格式 ECDSA 签名转换为原始 R||S 格式
     */
    private static function DERtoRawSignature($der) {
        $offset = 0;

        // SEQUENCE tag
        if (ord($der[$offset]) !== 0x30) {
            throw new Exception('无效的DER签名格式');
        }
        $offset++;

        // Total length
        $totalLength = ord($der[$offset]);
        $offset++;

        if ($totalLength & 0x80) {
            $numBytes = $totalLength & 0x7F;
            $totalLength = 0;
            for ($i = 0; $i < $numBytes; $i++) {
                $totalLength = ($totalLength << 8) | ord($der[$offset]);
                $offset++;
            }
        }

        // INTEGER tag for R
        if (ord($der[$offset]) !== 0x02) return '';
        $offset++;

        $rLength = ord($der[$offset]);
        $offset++;

        $r = substr($der, $offset, $rLength);
        $offset += $rLength;

        // INTEGER tag for S
        if (ord($der[$offset]) !== 0x02) return '';
        $offset++;

        $sLength = ord($der[$offset]);
        $offset++;

        $s = substr($der, $offset, $sLength);

        // 去除前导零（符号位填充）
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        // 填充到32字节
        $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    /**
     * 将原始公钥 (65 bytes, 0x04||x||y) 转换为 PEM 格式
     */
    private static function rawPublicKeyToPem($rawPublicKey) {
        // 构建 DER 编码的 SubjectPublicKeyInfo
        $der = "\x30\x59" .                    // SEQUENCE (89 bytes)
               "\x30\x13" .                    // SEQUENCE (19 bytes)
               "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01" .  // OID: ecPublicKey
               "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" .  // OID: prime256v1
               "\x03\x42\x00" .                // BIT STRING (66 bytes)
               $rawPublicKey;

        return "-----BEGIN PUBLIC KEY-----\n" .
               chunk_split(base64_encode($der), 64, "\n") .
               "-----END PUBLIC KEY-----\n";
    }

    /**
     * Base64url 编码
     */
    public static function base64urlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64url 解码
     */
    public static function base64urlDecode($data) {
        $padded = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=', STR_PAD_RIGHT);
        return base64_decode(strtr($padded, '-_', '+/'));
    }
}
