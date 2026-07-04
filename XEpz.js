// 判断在线状态函数
        function checkOnlineStatus() {
            const now = new Date();
            const currentHour = now.getHours();
            const currentMinute = now.getMinutes();
            const currentTimeInMinutes = currentHour * 60 + currentMinute;
            
            // 工作时间段：9:30-00:30
            const workStart = 9 * 60 + 30; // 9:30 -> 570分钟
            const workEnd = 0 * 60 + 30;   // 0:30 -> 30分钟
            
            // 获取状态元素
            const statusBadge = document.getElementById('statusBadge');
            
            // 判断是否在工作时间内
            let isOnline = false;
            
            if (workStart <= workEnd) {
                // 正常情况：开始时间 <= 结束时间
                isOnline = currentTimeInMinutes >= workStart && currentTimeInMinutes <= workEnd;
            } else {
                // 跨天情况：开始时间 > 结束时间（如 9:30 到 0:30）
                isOnline = currentTimeInMinutes >= workStart || currentTimeInMinutes <= workEnd;
            }
            
            // 更新状态显示
            if (isOnline) {
                statusBadge.textContent = '在线';
                statusBadge.style.color = '#05b374'; // 绿色
                statusBadge.classList.remove('away');
            } else {
                statusBadge.textContent = '下班（咨询）';
                statusBadge.style.color = '#f39909'; // 橙色
                statusBadge.classList.add('away');
            }
            
            // 控制台输出调试信息
            console.log(`当前时间: ${currentHour}:${currentMinute < 10 ? '0' + currentMinute : currentMinute}`);
            console.log(`工作时间: ${Math.floor(workStart/60)}:${workStart%60} - ${Math.floor(workEnd/60)}:${workEnd%60}`);
            console.log(`在线状态: ${isOnline ? '在线' : '暂离'}`);
        }
        
        // 工具栏相关功能
        let isToolbarVisible = false;
        
        // 切换工具栏显示/隐藏
        function toggleToolbar() {
            const toolbar = document.getElementById('toolbar');
            const footer = document.getElementById('footer');
            const overlay = document.getElementById('toolbar-overlay');
            
            if (isToolbarVisible) {
                // 隐藏工具栏
                toolbar.classList.remove('active');
                footer.classList.remove('toolbar-active');
                overlay.classList.remove('active');
                isToolbarVisible = false;
            } else {
                // 显示工具栏
                toolbar.classList.add('active');
                footer.classList.add('toolbar-active');
                overlay.classList.add('active');
                isToolbarVisible = true;
                
                // 隐藏其他浮动菜单
                hideAllFloatingMenus();
            }
        }
        
        // 隐藏工具栏
        function hideToolbar() {
            const toolbar = document.getElementById('toolbar');
            const footer = document.getElementById('footer');
            const overlay = document.getElementById('toolbar-overlay');
            
            toolbar.classList.remove('active');
            footer.classList.remove('toolbar-active');
            overlay.classList.remove('active');
            isToolbarVisible = false;
        }
        
        // 选择工具栏选项
        function selectToolbarItem(item) {
            alert(`您选择了：${item}`);
            hideToolbar();
            
            // 这里可以添加实际的功能代码
            if (item === '相册') {
                // 打开相册功能
                console.log('打开相册');
            } else if (item === '视频') {
                // 打开视频功能
                console.log('打开视频');
            } else if (item === '我的商品') {
                // 打开我的商品
                console.log('打开我的商品');
            }
        }
        
        // 隐藏所有浮动菜单
        function hideAllFloatingMenus() {
            if (card1 && card1.classList.contains('active')) {
                card1.classList.remove('active');
                setTimeout(() => {
                    card1.style.display = 'none';
                }, 300);
                icon1.className = 'noactivation';
            }
            if (card2 && card2.classList.contains('active')) {
                card2.classList.remove('active');
                setTimeout(() => {
                    card2.style.display = 'none';
                }, 300);
                icon2.className = 'noactivation';
            }
        }
        
        // 页面加载时检查状态
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化iconify图标
            if (typeof iconify === 'undefined') {
                console.warn('Iconify script not loaded');
            }
            
            // 检查在线状态
            checkOnlineStatus();
            
            // 设置定时器，每分钟检查一次状态
            setInterval(checkOnlineStatus, 60000); // 每分钟检查一次
            
            // 为底部按钮添加点击事件示例
            const buttons = document.querySelectorAll('.footer-button');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    console.log('按钮点击:', this.textContent);
                });
            });
            
            // 输入框回车发送消息示例
            const input = document.querySelector('.message-input');
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const message = this.value.trim();
                    if (message) {
                        console.log('发送消息:', message);
                        this.value = '';
                    }
                }
            });
        });

	    function close_shop() {
    document.getElementById('shop_levitation').style.display = 'none';
};

document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.getElementById('cont');
    const windElement = document.getElementById('Send_msg');

    function checkTextarea() {
        if (textarea && textarea.value.trim() !== '') {
            windElement.style.display = 'block';
        } else if (windElement) {
            windElement.style.display = 'none';
        }
    }

    if (textarea && windElement) {
        checkTextarea();
        textarea.addEventListener('input', checkTextarea);
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.getElementById('cont');
    const windElement = document.getElementById('Send_img');

    function checkTextarea() {
        if (textarea && textarea.value.trim() == '') {
            windElement.style.display = 'block';
        } else if (windElement) {
            windElement.style.display = 'none';
        }
    }

    if (textarea && windElement) {
        checkTextarea();
        textarea.addEventListener('input', checkTextarea);
    }
});

function hide_button() {
    if (Send_msg) Send_msg.style.display = 'none';
    if (Send_img) Send_img.style.display = 'block';
}

// 显示评价弹窗
function showAppraise() {
    document.getElementById('appraise-overlay').classList.add('active');
    document.getElementById('appraise').classList.add('active');
}

// 关闭评价弹窗
function closeAppraise() {
    document.getElementById('appraise-overlay').classList.remove('active');
    document.getElementById('appraise').classList.remove('active');
}

// 提交评价
function submitAppraise() {
    closeAppraise();
    document.getElementById('cgtipstext').innerHTML = "评价成功";
    if (document.getElementById('cgtips')) {
        document.getElementById('cgtips').style.display = 'flex';
        setTimeout(function() {
            document.getElementById('cgtips').style.display = 'none';
        }, 1500);
    }
}

function emoji1() {
    document.getElementById('emoji1').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-1-active.png';
    document.getElementById('emoji2').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-2.png';
    document.getElementById('emoji3').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-3.png';
    document.getElementById('emoji4').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-4.png';
    document.getElementById('emoji5').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-5.png';
}

function emoji2() {
    document.getElementById('emoji1').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-1.png';
    document.getElementById('emoji2').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-2-active.png';
    document.getElementById('emoji3').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-3.png';
    document.getElementById('emoji4').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-4.png';
    document.getElementById('emoji5').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-5.png';
}

function emoji3() {
    document.getElementById('emoji1').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-1.png';
    document.getElementById('emoji2').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-2.png';
    document.getElementById('emoji3').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-3-active.png';
    document.getElementById('emoji4').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-4.png';
    document.getElementById('emoji5').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-5.png';
}

function emoji4() {
    document.getElementById('emoji1').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-1.png';
    document.getElementById('emoji2').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-2.png';
    document.getElementById('emoji3').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-3.png';
    document.getElementById('emoji4').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-4-active.png';
    document.getElementById('emoji5').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-5.png';
}

function emoji5() {
    document.getElementById('emoji1').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-1.png';
    document.getElementById('emoji2').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-2.png';
    document.getElementById('emoji3').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-3.png';
    document.getElementById('emoji4').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-4.png';
    document.getElementById('emoji5').src = 'https://oss.pzds.com/mobileV3/icon-satisfaction-5-active.png';
}


// 简化版本
const button1 = document.getElementById('icon_button1');
const button2 = document.getElementById('icon_button2');
const icon1 = document.getElementById('icon1');
const icon2 = document.getElementById('icon2');
const card1 = document.getElementById('mini_crad');
const card2 = document.getElementById('mini_crad2');

// 初始化
if (card1) card1.style.display = 'none';
if (card2) card2.style.display = 'none';

// 处理第一个按钮
if (button1 && icon1 && card1) {
    button1.addEventListener('click', function(e) {
        e.stopPropagation();
        
        // 切换第一个弹窗
        if (card1.classList.contains('active')) {
            // 关闭第一个弹窗
            card1.classList.remove('active');
            setTimeout(() => {
                card1.style.display = 'none';
            }, 300);
            icon1.className = 'noactivation';
        } else {
            // 打开第一个弹窗，关闭第二个
            card1.style.display = 'flex';
            setTimeout(() => {
                card1.classList.add('active');
            }, 10);
            icon1.className = 'yesactivation';
            
            if (card2) {
                card2.classList.remove('active');
                setTimeout(() => {
                    card2.style.display = 'none';
                }, 300);
            }
            if (icon2) icon2.className = 'noactivation';
        }
    });
}

// 处理第二个按钮
if (button2 && icon2 && card2) {
    button2.addEventListener('click', function(e) {
        e.stopPropagation();
        
        // 切换第二个弹窗
        if (card2.classList.contains('active')) {
            // 关闭第二个弹窗
            card2.classList.remove('active');
            setTimeout(() => {
                card2.style.display = 'none';
            }, 300);
            icon2.className = 'noactivation';
        } else {
            // 打开第二个弹窗，关闭第一个
            card2.style.display = 'flex';
            setTimeout(() => {
                card2.classList.add('active');
            }, 10);
            icon2.className = 'yesactivation';
            
            if (card1) {
                card1.classList.remove('active');
                setTimeout(() => {
                    card1.style.display = 'none';
                }, 300);
            }
            if (icon1) icon1.className = 'noactivation';
        }
    });
}

// 点击页面其他地方关闭所有弹窗
document.addEventListener('click', function() {
    if (card1 && card1.classList.contains('active')) {
        card1.classList.remove('active');
        setTimeout(() => {
            card1.style.display = 'none';
        }, 300);
    }
    if (card2 && card2.classList.contains('active')) {
        card2.classList.remove('active');
        setTimeout(() => {
            card2.style.display = 'none';
        }, 300);
    }
    if (icon1) icon1.className = 'noactivation';
    if (icon2) icon2.className = 'noactivation';
    
    // 同时关闭工具栏
    hideToolbar();
});

function close_windows() {
    document.getElementById('windwo_101').style.display = 'none';
    document.getElementById('share2').style.display = 'none';
};

function open_windwos() {
    document.getElementById('windwo_101').style.display = 'flex';
    document.getElementById('share2').style.display = 'flex';
};

function shouqian() {
    document.getElementById('xuanxiang').innerHTML = "售前问题"
    document.getElementById('windwo_101').style.display = 'none';
    document.getElementById('share2').style.display = 'none';
}

function shouhou() {
    document.getElementById('xuanxiang').innerHTML = "售后问题"
    document.getElementById('windwo_101').style.display = 'none';
    document.getElementById('share2').style.display = 'none';
}

function jiaoti09() {
    document.getElementById('cgtipstext').innerHTML = "问题反馈成功";
    document.getElementById('cgtips').style.display = 'flex';
    setTimeout(function() {
        document.getElementById('cgtips').style.display = 'none';
        window.location.href = "../pz.php";
    }, 1500);
}

function shareclose01() {
    document.getElementById('mini_crad2').style.display = 'none'
    document.getElementById('shareclose01').style.display = 'none'
};

function gzbtn_1() {
    let gzbtn_1 = document.getElementById('gzbtn_1');
    gzbtn_1.style.backgroundColor = "#13bf78";
    gzbtn_1.innerHTML = "已关注"
};

function gzbtn_2() {
    let gzbtn_2 = document.getElementById('gzbtn_2');
    gzbtn_2.style.backgroundColor = "#13bf78";
    gzbtn_2.innerHTML = "已关注"
};

function gzbtn_3() {
    let gzbtn_3 = document.getElementById('gzbtn_3');
    gzbtn_3.style.backgroundColor = "#13bf78";
    gzbtn_3.innerHTML = "已关注"
};

function gzbtn_4() {
    let gzbtn_4 = document.getElementById('gzbtn_4');
    gzbtn_4.style.backgroundColor = "#13bf78";
    gzbtn_4.innerHTML = "已关注"
};