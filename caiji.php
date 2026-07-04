<?php 
// 闲鱼采集官方数据

// JSON响应辅助函数
function jsonResponse($success, $message, $data = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if($_GET['action'] == 'caiji'){
    // 验证URL
    $url = trim($_POST['cajiURL']);
    if(empty($url)){
        jsonResponse(false, '请输入闲鱼商品链接');
    }
    
    // 过滤URL防止XSS
    $url = htmlspecialchars($url, ENT_QUOTES);
    
    // 验证闲鱼官方链接
    $valid_domains = [
        'h5.m.goofish.com',
        'market.m.taobao.com',
        '2.taobao.com',
        'm.taobao.com'
    ];
    
    $is_valid = false;
    foreach($valid_domains as $domain){
        if(strpos($url, $domain) !== false){
            $is_valid = true;
            break;
        }
    }
    
    if(!$is_valid){
        jsonResponse(false, '采集数据失败,不是闲鱼官方完整的商品链接');
    }
    
    // 生成随机IP
    function randIp(){
        $ip_long = array(
            array('607649792', '608174079'), //36.56.0.0-36.63.255.255
            array('1038614528', '1039007743'), //61.232.0.0-61.237.255.255
            array('1783627776', '1784676351'), //106.80.0.0-106.95.255.255
            array('2035023872', '2035154943'), //121.76.0.0-121.77.255.255
            array('2078801920', '2079064063'), //123.232.0.0-123.235.255.255
            array('-1950089216', '-1948778497'), //139.196.0.0-139.215.255.255
            array('-1425539072', '-1425014785'), //171.8.0.0-171.15.255.255
            array('-1236271104', '-1235419137'), //182.80.0.0-182.92.255.255
            array('-770113536', '-768606209'), //210.25.0.0-210.47.255.255
            array('-569376768', '-564133889'), //222.16.0.0-222.95.255.255
        );
        $rand_key = mt_rand(0, 9);
        $ip = long2ip(mt_rand($ip_long[$rand_key][0], $ip_long[$rand_key][1]));
        return $ip;
    }
    
    // 封装请求
    function curlGet($url, $referer=0, $cookie=0, $getheader=0){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $header = array(
            "Accept: */*",
            "Accept-Encoding: gzip,deflate,sdch",
            "Accept-Language: zh-CN,zh;q=0.8",
            "user-agent: Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1",
            "X-FORWARDED-FOR: ".randIp(),
            "CLIENT-IP: ".randIp(),
            "Connection: close"
        );
        
        if($referer){
            curl_setopt($ch, CURLOPT_REFERER, $referer);
        } else {
            curl_setopt($ch, CURLOPT_REFERER, $url);
        }
        
        if($cookie){
            $header[] = "Cookie: ".$cookie;
        }
        
        if($getheader){
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }

    // 提取商品ID
    $itemId = '';
    if(preg_match('/id=(\d+)/', $url, $matches)){
        $itemId = $matches[1];
    }elseif(preg_match('/item\.html\?id=(\d+)/', $url, $matches)){
        $itemId = $matches[1];
    }
    
    if(empty($itemId)){
        jsonResponse(false, '无法从链接中提取商品ID');
    }

    // 生成时间戳和签名
    list($msec, $sec) = explode(' ', microtime());
    $time = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    
    // 第一次请求获取cookie和token
    $data = [
        'jsv' => "2.5.8",
        'appKey' => 12574478,
        't' => $time,
        'sign' => md5('00acba745ab1ca28c4a1c68f20e791**&' . $time . '&12574478&{"itemId":"' . $itemId . '"}'),
        'prefetch_key' => "detail_" . $itemId,
        'api' => "mtop.taobao.idle.awesome.detail",
        'v' => "1.0",
        'dataType' => "json",
        'valueType' => "string",
        'preventFallback' => true,
        'type' => "json",
        'data' => json_encode(["itemId" => $itemId])
    ];

    // 获取cookie和token
    $getcookie = curlGet('https://h5api.m.goofish.com/h5/mtop.taobao.idle.awesome.detail/1.0/?' . http_build_query($data), 
                         'https://h5.m.goofish.com/', 0, 1);
    
    // 提取cookie
    preg_match_all('/Set-Cookie:\s(.*?);/', $getcookie, $matches);
    if(empty($matches[1])){
        jsonResponse(false, '获取cookie失败');
    }
    
    $cookie = implode('; ', $matches[1]);
    
    // 提取token用于签名
    $token = '';
    if(preg_match('/_m_h5_tk=([^_]+)_/', $getcookie, $token_matches)){
        $token = $token_matches[1];
    }
    
    if(empty($token)){
        jsonResponse(false, '获取token失败');
    }
    
    // 使用正确的token生成签名
    $data['sign'] = md5($token . '&' . $time . '&12574478&{"itemId":"' . $itemId . '"}');
    
    // 获取商品数据
    $ret = curlGet('https://h5api.m.goofish.com/h5/mtop.taobao.idle.awesome.detail/1.0/?' . http_build_query($data), 
                  'https://h5.m.goofish.com/', $cookie);
    
    $arr = json_decode($ret, true);
    if(json_last_error() !== JSON_ERROR_NONE || !isset($arr['data'])){
        jsonResponse(false, '解析商品数据失败');
    }
    
    // 提取商品信息
    $imgs = "";
    if(isset($arr['data']['itemDO']['imageInfos'])){
        foreach ($arr['data']['itemDO']['imageInfos'] as $v) {
            if(isset($v["url"])){
                $imgs .= $v["url"] . ",";
            }
        }
        $imgs = rtrim($imgs, ",");
        $img = explode(",", $imgs);
    }else{
        $img = array();
    }
    
    // 卖家信息
    $nick = isset($arr['data']['sellerDO']['nick']) ? $arr['data']['sellerDO']['nick'] : '';
    $portraitUrl = isset($arr['data']['sellerDO']['portraitUrl']) ? $arr['data']['sellerDO']['portraitUrl'] : '';
    $address = isset($arr['data']['sellerDO']['publishCity']) ? $arr['data']['sellerDO']['publishCity'] : '';
    $sellerId = isset($arr['data']['sellerDO']['sellerId']) ? $arr['data']['sellerDO']['sellerId'] : '';
    $categoryId = isset($arr['data']['sellerDO']['categoryId']) ? $arr['data']['sellerDO']['categoryId'] : '';
    $lastVisitTime = isset($arr['data']['sellerDO']['lastVisitTime']) ? $arr['data']['sellerDO']['lastVisitTime'] : '';
    
    // 商品信息
    $title = isset($arr['data']['flowData']['floating']['components'][2]['data']['title']) ? 
             $arr['data']['flowData']['floating']['components'][2]['data']['title'] : '';
    $description = isset($arr['data']['itemDO']['desc']) ? $arr['data']['itemDO']['desc'] : '';
    $nowPrice = isset($arr['data']['flowData']['floating']['components'][1]['data']['price']) ? 
                $arr['data']['flowData']['floating']['components'][1]['data']['price'] : '';
    $pastPrice = isset($arr['data']['itemDO']['originalPrice']) ? $arr['data']['itemDO']['originalPrice'] : '';
    $wantCnt = isset($arr['data']['itemDO']['wantCnt']) ? $arr['data']['itemDO']['wantCnt'] : 0;
    $browseCnt = isset($arr['data']['itemDO']['browseCnt']) ? $arr['data']['itemDO']['browseCnt'] : 0;
    $favorCnt = isset($arr['data']['itemDO']['favorCnt']) ? $arr['data']['itemDO']['favorCnt'] : 0;
    
    $address1 = str_replace("发布于", "", $address);
    
    // 返回JSON数据
    jsonResponse(true, '采集成功', [
        'title' => $title,
        'price' => $nowPrice,
        'seller' => $nick,
        'coverImage' => !empty($img) ? $img[0] : '',
        'images' => $img,
        'description' => $description,
        'address' => $address1
    ]);
}
?>
