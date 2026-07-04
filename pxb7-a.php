<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>im聊天房间</title>
	<link rel="shortcut icon" href="/assets/img/pxb7.png" type="image/x-icon">
    <style>
       *{
           margin: 0;
           padding: 0;
       }
       body{
           background-color: rgba(244, 245, 246);
       }
       .wuyun-return{
           width: 100%;
           height: 45px;
           background-color: #FFF;
       }
       .wuyun-return-mar{
           padding-top: 10px;
           padding-left: 10px;
       }
       .wuyun-kefu{
           width: 90%;
           height: 100px;
           background-color: #FFF;
           margin-top: 15px;
           margin-left: 5%;
           border-radius: 10px;
       }
       .kefu-tp{
           width: 100%;
           padding: 24px 10px ;
       }
       .wuyun-tp{
           width: 50px;
           border-radius: 50%;
       }
       .wuyun-txt{
           position: fixed;
           font-size: 1rem;
           font-weight: bold;
           left: 24%;
       }
       .wuyun-fw{
           font-size: .75rem;
           color: rgb(153 153 153);
           padding-left:9px;
       }
       .wuyun-jyxx{
           width: 90%;
           height: 70px;
           background-color: #FFF;
           margin-top: 15px;
           margin-left: 5%;
           border-radius: 10px;
       }
       .jyxx-zt{
           font-size: 1rem;
           font-weight: 500;
           padding-left: 15px;
           line-height: 70px;
           text-align: center;
       }
        .switch {
            position: relative;
            display: inline-block;
            width: 32.8px;
            height: 20px;
            left: 60%;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 20px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #ff9933;
        }
        input:checked + .slider:before {
            transform: translateX(12.8px);
        }
    </style>
    
</head>
<body>
    <!--Murong顶部返回开始-->
    <div class="wuyun-return" onClick="location.href='javascript:history.back();'">
        <div class="wuyun-return-mar">
            <svg width="26" height="26" viewBox="0 0 32 32"><path d="M21.781 7.844l-9.063 8.594 9.063 8.594q0.25 0.25 0.25 0.609t-0.25 0.578q-0.25 0.25-0.578 0.25t-0.578-0.25l-9.625-9.125q-0.156-0.125-0.203-0.297t-0.047-0.359q0-0.156 0.047-0.328t0.203-0.297l9.625-9.125q0.25-0.25 0.578-0.25t0.578 0.25q0.25 0.219 0.25 0.578t-0.25 0.578z" fill="#000000"></path></svg>
        </div>
    </div>
    <!--头像客服信息开始-->
    <div class="wuyun-kefu">
        <div class="kefu-tp">
            <img src="/assets/img/px-kf.png" draggable="false" class="wuyun-tp">
            <span class="wuyun-txt">螃蟹官方客服</span>
            <span class="wuyun-fw">服务星级:</span>
            <svg t="1737958546019" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="6076" width="18" height="18"><path d="M512 39.384615l169.353846 295.384615 342.646154 63.015385-240.246154 248.123077L827.076923 984.615385l-315.076923-145.723077L196.923077 984.615385l43.323077-334.769231L0 401.723077l342.646154-63.015385L512 39.384615" fill="#F3D958" p-id="6077"></path></svg>
           <svg t="1737958546019" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="6076" width="18" height="18"><path d="M512 39.384615l169.353846 295.384615 342.646154 63.015385-240.246154 248.123077L827.076923 984.615385l-315.076923-145.723077L196.923077 984.615385l43.323077-334.769231L0 401.723077l342.646154-63.015385L512 39.384615" fill="#F3D958" p-id="6077"></path></svg>
           <svg t="1737958546019" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="6076" width="18" height="18"><path d="M512 39.384615l169.353846 295.384615 342.646154 63.015385-240.246154 248.123077L827.076923 984.615385l-315.076923-145.723077L196.923077 984.615385l43.323077-334.769231L0 401.723077l342.646154-63.015385L512 39.384615" fill="#F3D958" p-id="6077"></path></svg>
           <svg t="1737958546019" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="6076" width="18" height="18"><path d="M512 39.384615l169.353846 295.384615 342.646154 63.015385-240.246154 248.123077L827.076923 984.615385l-315.076923-145.723077L196.923077 984.615385l43.323077-334.769231L0 401.723077l342.646154-63.015385L512 39.384615" fill="#F3D958" p-id="6077"></path></svg>
           <svg t="1737958546019" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="6076" width="18" height="18"><path d="M512 39.384615l169.353846 295.384615 342.646154 63.015385-240.246154 248.123077L827.076923 984.615385l-315.076923-145.723077L196.923077 984.615385l43.323077-334.769231L0 401.723077l342.646154-63.015385L512 39.384615" fill="#F3D958" p-id="6077"></path></svg>
            <span style="padding-left:5px">5</span>
        </div>
    </div>
    <!--交易信息开始-->
    <div class="wuyun-jyxx" onClick="location.href='/Pxb7DD'">
        <span class="jyxx-zt">交易信息</span>
        <svg t="1737959871827" style="padding-left:65%;" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2443" width="24" height="24"><path d="M562.005333 512l-181.034666 181.034667a42.666667 42.666667 0 1 0 60.330666 60.330666l211.2-211.2a42.666667 42.666667 0 0 0 0-60.330666l-211.2-211.2a42.666667 42.666667 0 1 0-60.330666 60.330666L562.005333 512z" fill="#5D6E7F" p-id="2444"></path></svg>
    </div>
    <!--置顶开始-->
    <div class="wuyun-jyxx">
        <span class="jyxx-zt">置顶交易</span>
        <label class="switch">
        <input type="checkbox" id="toggleSwitch">
        <span class="slider"></span>
    </label>
    </div>
    <!--弹出层-->
    <div class="ttc" id="cg" style="display:none;">
        <span class="zd">聊天置顶成功</span>
    </div>
    <div class="ttc" id="qx" style="display:none;">
        <span class="zd">取消置顶成功</span>
    </div>
     <style>
        .ttc {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            width: 35%;
            max-width: 400px;
            height: 40px;
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .zd {
            color: #fff;
            text-align: center;
            margin: 0;
            padding: 0;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleSwitch = document.getElementById('toggleSwitch');
            const slider = document.querySelector('.slider');

            toggleSwitch.addEventListener('change', function() {
                if (this.checked) {
                   document.getElementById('cg').style.display ='';
                   document.getElementById('qx').style.display ='none';
                   setTimeout("cg.style.display='none';",2000);
                } else {
                   document.getElementById('qx').style.display ='';
                   document.getElementById('cg').style.display ='none';
                   setTimeout("qx.style.display='none';",2000);
                }
            });
        });
        
    </script>
    
</body>
</html>